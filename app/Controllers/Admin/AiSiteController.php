<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Controllers\InstallController;
use Core\Ai;
use Core\AiSite;
use Core\AiSiteSchema;
use Core\Config;
use Core\Database;
use Core\View;
use Models\User;
use PDO;

/**
 * Backend des KI-Webseiten-Modus: ein Anweisungs-Textfeld + Bild-Upload.
 * Die KI erzeugt statische HTML/CSS/jQuery-Dateien (kein CMS, keine DB).
 * Gedächtnis liegt dateibasiert (AiSite-Verlauf + Dateisystem selbst).
 */
class AiSiteController extends AdminController
{
    private const ALLOWED_IMG = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
        'image/webp' => 'webp', 'image/svg+xml' => 'svg',
    ];

    public function index(): void
    {
        AiSite::ensureDirs();
        $data = [
            'title' => 'KI-Webseite',
            'flash' => flash(),
            'history' => AiSite::history(),
            'files' => AiSite::listFiles(),
            'images' => AiSite::listImages(),
            'siteName' => (string) Config::sub('ai', 'admin_user', ''),
        ];
        View::render('admin/ai-site/index', $data, 'admin/_shell_ai');
    }

    /** POST /admin/ai-site/generate – ein kompletter KI-Durchlauf. */
    public function generate(): void
    {
        header('Content-Type: application/json');
        set_time_limit(300);

        $input = json_decode(file_get_contents('php://input') ?: '', true);
        $userText = trim((string) ($input['message'] ?? ''));
        if ($userText === '') {
            echo json_encode(['ok' => false, 'error' => 'Bitte gib eine Anweisung ein.']);
            return;
        }

        // Nachrichten = gespeicherter Text-Verlauf (Gedächtnis) + neue Anweisung.
        $messages = [];
        foreach (AiSite::history() as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $text = trim((string) ($turn['text'] ?? ''));
            if ($text !== '') {
                $messages[] = ['role' => $role, 'content' => $text];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userText];

        $actions = [];
        $balance = null;
        try {
            $system = AiSiteSchema::systemPrompt();
            $tools = AiSiteSchema::tools();

            for ($round = 0; $round < 16; $round++) {
                $response = Ai::chat($messages, $tools, $system);
                $balance = $response['balance'] ?? $balance;
                $content = is_array($response['content'] ?? null) ? $response['content'] : [];
                $messages[] = ['role' => 'assistant', 'content' => $content];

                if (($response['stop_reason'] ?? '') !== 'tool_use') {
                    $text = '';
                    foreach ($content as $part) {
                        if (($part['type'] ?? '') === 'text') {
                            $text .= $part['text'];
                        }
                    }
                    $reply = trim($text) !== '' ? trim($text) : 'Erledigt.';
                    AiSite::addHistory('user', $userText);
                    AiSite::addHistory('assistant', $reply);
                    echo json_encode(['ok' => true, 'text' => $reply, 'actions' => $actions, 'balance' => $balance, 'files' => AiSite::listFiles()], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $results = [];
                foreach ($content as $part) {
                    if (($part['type'] ?? '') === 'tool_use') {
                        $results[] = [
                            'type' => 'tool_result',
                            'tool_use_id' => (string) $part['id'],
                            'content' => $this->runTool((string) $part['name'], is_array($part['input'] ?? null) ? $part['input'] : [], $actions, $balance),
                        ];
                    }
                }
                $messages[] = ['role' => 'user', 'content' => $results];
            }

            $reply = 'Ich habe in mehreren Schritten gearbeitet und die Schrittgrenze erreicht. Bitte fasse den Rest kurz als Folgeauftrag zusammen.';
            AiSite::addHistory('user', $userText);
            AiSite::addHistory('assistant', $reply);
            echo json_encode(['ok' => true, 'text' => $reply, 'actions' => $actions, 'balance' => $balance, 'files' => AiSite::listFiles()], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'balance' => $balance], JSON_UNESCAPED_UNICODE);
        }
    }

    /** Führt ein einzelnes Werkzeug aus und liefert den Text fürs tool_result. */
    private function runTool(string $name, array $input, array &$actions, ?int &$balance): string
    {
        switch ($name) {
            case 'write_file':
                $path = (string) ($input['path'] ?? '');
                $err = AiSite::writeFile($path, (string) ($input['content'] ?? ''));
                if ($err !== null) {
                    return 'FEHLER: ' . $err;
                }
                $actions[] = ['type' => 'file', 'label' => 'Datei gespeichert: ' . $path];
                return 'OK – Datei "' . $path . '" gespeichert.';

            case 'read_file':
                $data = AiSite::readFile((string) ($input['path'] ?? ''));
                if ($data === null) {
                    return 'FEHLER: Datei nicht gefunden.';
                }
                return mb_substr($data, 0, 60000);

            case 'list_files':
                $files = AiSite::listFiles();
                if ($files === []) {
                    return 'Noch keine Dateien vorhanden.';
                }
                return implode("\n", array_map(static fn ($f) => $f['path'] . ' (' . $f['size'] . ' B)', $files));

            case 'delete_file':
                $path = (string) ($input['path'] ?? '');
                if (!AiSite::deleteFile($path)) {
                    return 'FEHLER: Datei konnte nicht gelöscht werden.';
                }
                $actions[] = ['type' => 'file', 'label' => 'Datei gelöscht: ' . $path];
                return 'OK – "' . $path . '" gelöscht.';

            case 'generate_image':
                try {
                    $res = Ai::image((string) ($input['prompt'] ?? ''));
                    $balance = $res['balance'] ?? $balance;
                    $bytes = base64_decode((string) ($res['image_b64'] ?? ''), true);
                    if ($bytes === false || $bytes === '') {
                        return 'FEHLER: Bild konnte nicht erzeugt werden.';
                    }
                    $url = AiSite::storeImage($bytes, 'ki-bild', 'png');
                    if ($url === null) {
                        return 'FEHLER: Bild konnte nicht gespeichert werden.';
                    }
                    $actions[] = ['type' => 'image', 'label' => 'Bild erzeugt', 'url' => $url];
                    return 'OK – Bild gespeichert unter: ' . $url;
                } catch (\Throwable $e) {
                    return 'FEHLER: ' . $e->getMessage();
                }

            case 'list_images':
                $imgs = AiSite::listImages();
                if ($imgs === []) {
                    return 'Keine Bilder vorhanden.';
                }
                return implode("\n", array_map(static fn ($i) => $i['url'], $imgs));

            default:
                return 'FEHLER: Unbekanntes Werkzeug "' . $name . '".';
        }
    }

    /** POST /admin/ai-site/upload – Bild datenbank-frei speichern. */
    public function upload(): void
    {
        header('Content-Type: application/json');
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            echo json_encode(['ok' => false, 'error' => 'Kein gültiger Upload.']);
            return;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!isset(self::ALLOWED_IMG[$mime])) {
            echo json_encode(['ok' => false, 'error' => 'Nur Bilder (JPG, PNG, GIF, WebP, SVG) erlaubt.']);
            return;
        }
        $bytes = (string) file_get_contents($file['tmp_name']);
        $url = AiSite::storeImage($bytes, (string) ($file['name'] ?? 'bild'), self::ALLOWED_IMG[$mime]);
        if ($url === null) {
            echo json_encode(['ok' => false, 'error' => 'Speichern fehlgeschlagen.']);
            return;
        }
        echo json_encode(['ok' => true, 'url' => $url]);
    }

    public function clearHistory(): void
    {
        AiSite::clearHistory();
        flash('success', 'Verlauf gelöscht. (Deine Website-Dateien bleiben erhalten.)');
        redirect('/admin');
    }

    /* ---------- Wechsel zurück in den CMS-Modus ---------- */

    public function setupCmsForm(): void
    {
        $hasDb = is_array(Config::get('db'));
        View::render('admin/ai-site/setup-cms', [
            'title' => 'Zum CMS-Modus wechseln',
            'flash' => flash(),
            'hasDb' => $hasDb,
        ], 'admin/_shell_ai');
    }

    public function switchToCms(): void
    {
        $cfg = Config::all();

        // Fall A: Es existiert bereits eine DB-Konfiguration → einfach umschalten.
        if (is_array($cfg['db'] ?? null) && trim($_POST['host'] ?? '') === '') {
            $cfg['mode'] = 'cms';
            Config::save($cfg);
            flash('success', 'Zurück im CMS-Modus. Die KI-Webseite bleibt gespeichert und ist jederzeit wieder aktivierbar.');
            redirect('/admin');
        }

        // Fall B: DB einrichten (Formular gesendet).
        $host = trim($_POST['host'] ?? '');
        $port = (int) ($_POST['port'] ?? 3306);
        $name = trim($_POST['name'] ?? '');
        $user = trim($_POST['user'] ?? '');
        $pass = (string) ($_POST['pass'] ?? '');
        $siteName = trim($_POST['site_name'] ?? '');
        $adminUser = trim($_POST['username'] ?? '');
        $adminPass = (string) ($_POST['password'] ?? '');

        if ($host === '' || $name === '' || $user === '' || $siteName === '' || $adminUser === '' || strlen($adminPass) < 8) {
            flash('error', 'Bitte Datenbank-Zugang, Seitenname, Admin-Benutzer und ein Passwort (mind. 8 Zeichen) ausfüllen.');
            redirect('/admin/ai-site/setup-cms');
        }

        try {
            try {
                $pdo = Database::connect($host, $port, $name, $user, $pass);
            } catch (\PDOException) {
                $server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port), $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $pdo = Database::connect($host, $port, $name, $user, $pass);
            }
            Database::createSchema($pdo);
            User::create($pdo, $adminUser, $adminPass);
            InstallController::seed($pdo, $siteName);
        } catch (\PDOException $e) {
            flash('error', 'Datenbank-Einrichtung fehlgeschlagen: ' . $e->getMessage());
            redirect('/admin/ai-site/setup-cms');
        }

        $cfg['mode'] = 'cms';
        $cfg['db'] = ['host' => $host, 'port' => $port, 'name' => $name, 'user' => $user, 'pass' => $pass];
        Config::save($cfg);
        flash('success', 'CMS eingerichtet und aktiviert. Die KI-Webseite bleibt gespeichert.');
        redirect('/admin');
    }
}
