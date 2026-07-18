<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Mailer;
use Models\Page;
use Models\Setting;

class SettingsController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    public function index(): void
    {
        $this->view('admin/settings', [
            'title' => 'Einstellungen',
            'active' => 'settings',
            'siteName' => Setting::get('site_name', 'Meine Website'),
            'homePage' => (int) Setting::get('home_page', '0'),
            'contactEmail' => Setting::get('contact_email', ''),
            'pages' => Page::tree(),
            'mail' => [
                'transport' => Setting::get('mail_transport', 'mail'),
                'from' => Setting::get('mail_from', ''),
                'from_name' => Setting::get('mail_from_name', ''),
                'smtp_host' => Setting::get('smtp_host', ''),
                'smtp_port' => Setting::get('smtp_port', '587'),
                'smtp_encryption' => Setting::get('smtp_encryption', 'tls'),
                'smtp_user' => Setting::get('smtp_user', ''),
                'smtp_pass' => Setting::get('smtp_pass', ''),
            ],
        ]);
    }

    public function save(): void
    {
        $siteName = trim($_POST['site_name'] ?? '');
        if ($siteName !== '') {
            Setting::set('site_name', $siteName);
        }
        Setting::set('home_page', (string) (int) ($_POST['home_page'] ?? 0));

        $contactEmail = trim($_POST['contact_email'] ?? '');
        if ($contactEmail === '' || filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            Setting::set('contact_email', $contactEmail);
        }

        // Sprachen (z. B. "de,en") – nur gültige zweibuchstabige Codes.
        $langs = array_values(array_unique(array_filter(array_map(
            static fn (string $l): string => strtolower(trim($l)),
            explode(',', $_POST['languages'] ?? 'de')
        ), static fn (string $l): bool => preg_match('/^[a-z]{2}$/', $l) === 1)));
        Setting::set('languages', $langs !== [] ? implode(',', $langs) : 'de');

        Setting::set('cache_enabled', isset($_POST['cache_enabled']) ? '1' : '0');
        \Core\Cache::clear();

        // KI-Assistent: Dienst-URL + Lizenzschlüssel.
        $aiUrl = trim($_POST['ai_service_url'] ?? '');
        if ($aiUrl === '' || preg_match('~^https?://~i', $aiUrl)) {
            Setting::set('ai_service_url', $aiUrl);
        }
        Setting::set('ai_license_key', trim($_POST['ai_license_key'] ?? ''));

        $this->saveMailSettings();

        // Zweiter Absende-Knopf: speichern UND Testmail schicken.
        if (($_POST['action'] ?? '') === 'test') {
            $this->sendTestMail();
            redirect('/admin/settings');
        }

        flash('success', 'Einstellungen gespeichert.');
        redirect('/admin/settings');
    }

    private function saveMailSettings(): void
    {
        $transport = ($_POST['mail_transport'] ?? 'mail') === 'smtp' ? 'smtp' : 'mail';
        Setting::set('mail_transport', $transport);

        $from = trim($_POST['mail_from'] ?? '');
        if ($from === '' || filter_var($from, FILTER_VALIDATE_EMAIL)) {
            Setting::set('mail_from', $from);
        }
        Setting::set('mail_from_name', trim($_POST['mail_from_name'] ?? ''));

        Setting::set('smtp_host', trim($_POST['smtp_host'] ?? ''));
        $port = (int) ($_POST['smtp_port'] ?? 587);
        Setting::set('smtp_port', (string) ($port > 0 && $port <= 65535 ? $port : 587));
        $encryption = $_POST['smtp_encryption'] ?? 'tls';
        Setting::set('smtp_encryption', in_array($encryption, ['none', 'ssl', 'tls'], true) ? $encryption : 'tls');
        Setting::set('smtp_user', trim($_POST['smtp_user'] ?? ''));
        // Leeres Passwortfeld = bestehendes Passwort behalten.
        $pass = (string) ($_POST['smtp_pass'] ?? '');
        if ($pass !== '') {
            Setting::set('smtp_pass', $pass);
        }
        if (isset($_POST['smtp_pass_clear'])) {
            Setting::set('smtp_pass', '');
        }
    }

    private function sendTestMail(): void
    {
        $to = trim($_POST['test_email'] ?? '') ?: Setting::get('contact_email', '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Bitte eine gültige Empfängeradresse für die Testmail angeben.');
            return;
        }

        $transport = Setting::get('mail_transport', 'mail') === 'smtp'
            ? 'SMTP (' . Setting::get('smtp_host', '') . ')'
            : 'PHP mail() über den Server';

        $body = "Glückwunsch! 🎉\n\nDiese Testmail wurde erfolgreich über \"$transport\" versendet.\n"
            . 'Website: ' . Setting::get('site_name', '') . "\n"
            . 'Zeitpunkt: ' . date('d.m.Y H:i:s') . "\n\n"
            . 'Der E-Mail-Versand deines CMS funktioniert.';

        $error = Mailer::send($to, 'Testmail von ' . Setting::get('site_name', 'deinem CMS'), $body);
        if ($error === null) {
            flash('success', 'Einstellungen gespeichert – Testmail an ' . $to . ' wurde versendet! Prüfe dein Postfach (ggf. auch den Spam-Ordner).');
        } else {
            flash('error', 'Einstellungen gespeichert, aber die Testmail schlug fehl: ' . $error);
        }
    }
}
