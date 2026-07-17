<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Updater;
use Models\Setting;

class UpdateController extends AdminController
{
    public function index(): void
    {
        $this->view('admin/update', [
            'title' => 'Updates',
            'active' => 'update',
            'currentVersion' => Updater::currentVersion(),
            'remoteVersion' => $_SESSION['update_remote_version'] ?? null,
            'zipUrl' => Updater::zipUrl(),
            'versionUrl' => Updater::versionUrl(),
        ]);
    }

    public function check(): void
    {
        $this->saveUrls();
        $remote = Updater::remoteVersion();
        if ($remote === null) {
            unset($_SESSION['update_remote_version']);
            flash('error', 'Die verfügbare Version konnte nicht abgerufen werden. Ist das Repository öffentlich und die Versions-URL korrekt?');
        } else {
            $_SESSION['update_remote_version'] = $remote;
            if (version_compare($remote, Updater::currentVersion(), '>')) {
                flash('success', 'Update verfügbar: Version ' . $remote . ' (installiert: ' . Updater::currentVersion() . ').');
            } else {
                flash('success', 'Deine Installation ist aktuell (Version ' . Updater::currentVersion() . ').');
            }
        }
        redirect('/admin/update');
    }

    public function run(): void
    {
        $this->saveUrls();
        $error = Updater::apply();
        unset($_SESSION['update_remote_version']);
        if ($error !== null) {
            flash('error', 'Update fehlgeschlagen: ' . $error);
        } else {
            flash('success', 'Update installiert! Diese Installation läuft jetzt auf Version ' . Updater::currentVersion() . '.');
        }
        redirect('/admin/update');
    }

    private function saveUrls(): void
    {
        foreach (['update_zip_url' => 'zip_url', 'update_version_url' => 'version_url'] as $setting => $field) {
            $value = trim($_POST[$field] ?? '');
            if ($value === '' || preg_match('~^https://~i', $value)) {
                Setting::set($setting, $value);
            }
        }
    }
}
