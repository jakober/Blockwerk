<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Ai;
use Core\AiSite;
use Core\Config;

/**
 * CMS-seitige Umschaltung in den KI-Webseiten-Modus (und Info dazu). Der
 * Rückweg (KI → CMS) läuft über AiSiteController. Beim Umschalten bleibt der
 * `db`-Block erhalten, sodass das CMS jederzeit wieder aktivierbar ist.
 */
class ModeController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    public function index(): void
    {
        $this->view('admin/mode', [
            'title' => 'Website-Modus',
            'active' => 'mode',
        ]);
    }

    public function switchToAi(): void
    {
        $license = trim($_POST['license'] ?? '');
        $serviceUrl = rtrim(trim($_POST['service_url'] ?? ''), '/');
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $passwordRepeat = (string) ($_POST['password_repeat'] ?? '');
        $siteName = trim($_POST['site_name'] ?? '') ?: (string) \Models\Setting::get('site_name', 'Meine Website');

        if ($license === '' || $username === '' || strlen($password) < 8) {
            flash('error', 'Bitte Lizenzschlüssel, Backend-Benutzer und ein Passwort (mind. 8 Zeichen) angeben.');
            redirect('/admin/mode');
        }
        if ($password !== $passwordRepeat) {
            flash('error', 'Die Passwörter stimmen nicht überein.');
            redirect('/admin/mode');
        }
        $check = Ai::checkLicense($license, $serviceUrl);
        if ($check['reachable'] && !$check['ok']) {
            flash('error', 'Lizenz abgelehnt: ' . ($check['error'] ?? 'ungültig'));
            redirect('/admin/mode');
        }

        $cfg = Config::all();
        $cfg['mode'] = 'ai';
        $cfg['ai'] = [
            'license_key' => $license,
            'admin_user' => $username,
            'admin_pass_hash' => password_hash($password, PASSWORD_DEFAULT),
        ];
        if ($serviceUrl !== '') {
            $cfg['ai']['service_url'] = $serviceUrl;
        }
        // db-Block bleibt erhalten (Unabhängigkeit / späterer Rückwechsel).
        if (!Config::save($cfg)) {
            flash('error', 'Die Konfigurationsdatei konnte nicht geschrieben werden (Schreibrechte für config/?).');
            redirect('/admin/mode');
        }
        AiSite::scaffold($siteName);

        flash('success', 'KI-Webseiten-Modus aktiviert. Dein CMS bleibt gespeichert und ist über „Zum CMS-Modus wechseln" wieder aktivierbar.');
        redirect('/admin');
    }
}
