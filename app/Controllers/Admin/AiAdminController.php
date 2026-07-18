<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Updater;
use Models\Setting;

/**
 * KI-Verwaltung – nur auf den Anbieter-Domains sichtbar. Verwaltet den
 * mitinstallierten zentralen KI-Dienst (ai-server/) direkt aus dem
 * Backend: API-Schlüssel/Preise in die Dienst-Konfiguration schreiben
 * und Kunden-Lizenzen anlegen, aufladen, sperren.
 */
class AiAdminController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        if (!Updater::isVendorHost()) {
            flash('error', 'Die KI-Verwaltung ist nur auf der Anbieter-Domain verfügbar.');
            redirect('/admin');
        }
    }

    private function serverDir(): string
    {
        return BASE_PATH . '/ai-server';
    }

    private function serviceAvailable(): bool
    {
        return is_file($this->serverDir() . '/index-lib.php');
    }

    private function loadConfig(): array
    {
        $file = $this->serverDir() . '/config.php';
        $config = is_file($file) ? require $file : [];
        return is_array($config) ? $config : [];
    }

    private function licenses(): array
    {
        if (!$this->serviceAvailable() || !extension_loaded('pdo_sqlite')) {
            return [];
        }
        require_once $this->serverDir() . '/index-lib.php';
        return aiDb()->query('SELECT * FROM licenses ORDER BY created_at DESC')->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function index(): void
    {
        $config = $this->loadConfig();
        $this->view('admin/ai/admin', [
            'title' => 'KI-Verwaltung',
            'active' => 'ai-admin',
            'available' => $this->serviceAvailable(),
            'sqliteOk' => extension_loaded('pdo_sqlite'),
            'configured' => is_file($this->serverDir() . '/config.php'),
            'config' => $config,
            'licenses' => $this->licenses(),
            'ownKey' => Setting::get('ai_license_key', ''),
        ]);
    }

    /** API-Schlüssel & Preise speichern → schreibt ai-server/config.php. */
    public function saveConfig(): void
    {
        if (!$this->serviceAvailable()) {
            flash('error', 'Das Verzeichnis ai-server/ fehlt – bitte zuerst das CMS-Update auf dieser Domain ausführen.');
            redirect('/admin/ai-admin');
        }
        $old = $this->loadConfig();

        // Leere Schlüssel-Felder = vorhandenen Wert behalten (Maskierung).
        $keep = static fn (string $field, string $oldValue): string =>
            trim($_POST[$field] ?? '') !== '' ? trim($_POST[$field]) : $oldValue;

        $config = [
            'anthropic_key' => $keep('anthropic_key', (string) ($old['anthropic_key'] ?? '')),
            'model' => trim($_POST['model'] ?? '') ?: (string) ($old['model'] ?? 'claude-sonnet-5'),
            'openai_key' => $keep('openai_key', (string) ($old['openai_key'] ?? '')),
            'image_model' => trim($_POST['image_model'] ?? '') ?: (string) ($old['image_model'] ?? 'gpt-image-1'),
            'image_token_price' => max(0, (int) ($_POST['image_token_price'] ?? ($old['image_token_price'] ?? 25000))),
            'admin_password' => $keep('admin_password', (string) ($old['admin_password'] ?? bin2hex(random_bytes(12)))),
            'rate_limit_per_minute' => max(1, (int) ($_POST['rate_limit_per_minute'] ?? ($old['rate_limit_per_minute'] ?? 20))),
            'mock' => !empty($_POST['mock']) ? true : false,
        ];

        $file = $this->serverDir() . '/config.php';
        $php = "<?php\n// Automatisch über die KI-Verwaltung im Backend geschrieben.\nreturn " . var_export($config, true) . ";\n";
        if (file_put_contents($file, $php) === false) {
            flash('error', 'Die Konfiguration konnte nicht geschrieben werden – bitte Schreibrechte für ai-server/ prüfen.');
            redirect('/admin/ai-admin');
        }

        // OPcache würde sonst noch kurz die alte Datei liefern – sofort
        // invalidieren und zur Kontrolle frisch zurücklesen.
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        $check = require $file;
        if (!is_array($check) || ($check['anthropic_key'] ?? null) !== $config['anthropic_key']) {
            flash('error', 'Die Konfiguration wurde geschrieben, konnte aber nicht zuverlässig zurückgelesen werden – bitte Schreibrechte und OPcache-Einstellungen prüfen.');
        } else {
            flash('success', 'KI-Dienst-Konfiguration gespeichert – der Dienst ist sofort aktiv.');
        }
        redirect('/admin/ai-admin');
    }

    /** Lizenz-Aktionen: anlegen, aufladen, sperren/aktivieren, hier nutzen. */
    public function license(): void
    {
        if (!$this->serviceAvailable() || !extension_loaded('pdo_sqlite')) {
            flash('error', 'Der KI-Dienst ist auf diesem Server nicht verfügbar.');
            redirect('/admin/ai-admin');
        }
        require_once $this->serverDir() . '/index-lib.php';
        $db = aiDb();
        $action = $_POST['action'] ?? '';
        $key = trim($_POST['key'] ?? '');

        switch ($action) {
            case 'create':
                $key = 'bw-' . bin2hex(random_bytes(12));
                $db->prepare('INSERT INTO licenses (license_key, name, tokens_total) VALUES (?, ?, ?)')
                    ->execute([$key, trim($_POST['name'] ?? '') ?: 'Ohne Namen', max(0, (int) ($_POST['tokens'] ?? 0))]);
                flash('success', 'Lizenz angelegt: ' . $key);
                break;
            case 'topup':
                $tokens = max(0, (int) ($_POST['tokens'] ?? 0));
                $db->prepare('UPDATE licenses SET tokens_total = tokens_total + ? WHERE license_key = ?')->execute([$tokens, $key]);
                flash('success', number_format($tokens, 0, ',', '.') . ' Tokens aufgeladen.');
                break;
            case 'toggle':
                $db->prepare('UPDATE licenses SET active = 1 - active WHERE license_key = ?')->execute([$key]);
                flash('success', 'Lizenz-Status geändert.');
                break;
            case 'use':
                Setting::set('ai_license_key', $key);
                Setting::set('ai_service_url', '');
                flash('success', 'Diese Lizenz wird jetzt vom KI-Assistenten dieser Installation genutzt.');
                break;
            default:
                flash('error', 'Unbekannte Aktion.');
        }
        redirect('/admin/ai-admin');
    }
}
