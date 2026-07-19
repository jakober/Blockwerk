<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Updater;

class UpdateController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    public function index(): void
    {
        $done = $_SESSION['update_done'] ?? null;
        unset($_SESSION['update_done']);

        $this->view('admin/update', [
            'title' => 'Updates',
            'active' => 'update',
            'currentVersion' => Updater::currentVersion(),
            // Verfügbare Version direkt aus dem Cache anzeigen (kein Netz, blockiert
            // die Seite nicht) – der Hintergrund-Check füllt den Cache; „Suchen"
            // erzwingt eine sofortige Prüfung.
            'remoteVersion' => Updater::cachedRemote(),
            'updateDone' => $done,
            'changelog' => is_array($done) ? $this->changelogSince($done['from']) : [],
        ]);
    }

    /**
     * Hintergrund-Check per XHR (nach dem Laden der Seite aufgerufen). Prüft bei
     * Fälligkeit online und liefert die verfügbare neuere Version als JSON – ohne
     * den Seitenaufbau zu blockieren.
     */
    public function status(): void
    {
        $force = ($_GET['force'] ?? '') === '1';
        // Bei ?force=1 eine frische Online-Prüfung erzwingen (für die Fehlersuche).
        $available = $force ? Updater::cachedRemoteVersion(true) : Updater::autoCheck();
        if ($force) {
            $available = Updater::updateAvailable();
        }

        header('Content-Type: application/json; charset=utf-8');
        $payload = [
            'current' => Updater::currentVersion(),
            'available' => $available,
            'cached' => Updater::cachedRemote(),
            'checkedAgoSec' => time() - (int) \Models\Setting::get('update_checked_at', '0'),
            'checkedVersion' => (string) \Models\Setting::get('update_checked_version', ''),
        ];
        if ($force) {
            // Klartext-Diagnose: sind die GitHub-Quellen erreichbar?
            $payload['diagnose'] = Updater::diagnose();
        }
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function check(): void
    {
        // Erzwungene, frische Prüfung.
        if (Updater::cachedRemoteVersion(true) === null) {
            flash('error', 'Die verfügbare Version konnte gerade nicht abgerufen werden – bitte später noch einmal versuchen.');
        }
        redirect('/admin/update');
    }

    public function run(): void
    {
        $from = Updater::currentVersion();
        $error = Updater::apply();
        unset($_SESSION['update_remote_version']);
        if ($error !== null) {
            flash('error', 'Update fehlgeschlagen: ' . $error);
        } else {
            $_SESSION['update_done'] = ['from' => $from, 'to' => Updater::currentVersion()];
        }
        redirect('/admin/update');
    }

    /**
     * Liest aus CHANGELOG.md alle Einträge der Versionen, die neuer sind
     * als $since – für die „Das ist neu“-Liste nach einem Update.
     */
    private function changelogSince(string $since): array
    {
        $file = dirname(__DIR__, 3) . '/CHANGELOG.md';
        if (!is_file($file)) {
            return [];
        }
        $sections = [];
        $version = null;
        foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $line) {
            if (preg_match('/^##\s*([0-9]+\.[0-9]+\.[0-9]+)/', $line, $m)) {
                $version = version_compare($m[1], $since, '>') ? $m[1] : null;
                continue;
            }
            if ($version !== null && preg_match('/^-\s+(.*)$/', trim($line), $m)) {
                $sections[$version][] = $m[1];
            }
        }
        return array_slice($sections, 0, 8, true);
    }
}
