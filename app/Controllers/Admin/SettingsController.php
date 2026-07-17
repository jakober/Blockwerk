<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Models\Page;
use Models\Setting;

class SettingsController extends AdminController
{
    public function index(): void
    {
        $this->view('admin/settings', [
            'title' => 'Einstellungen',
            'active' => 'settings',
            'siteName' => Setting::get('site_name', 'Meine Website'),
            'homePage' => (int) Setting::get('home_page', '0'),
            'pages' => Page::tree(),
        ]);
    }

    public function save(): void
    {
        $siteName = trim($_POST['site_name'] ?? '');
        if ($siteName !== '') {
            Setting::set('site_name', $siteName);
        }
        Setting::set('home_page', (string) (int) ($_POST['home_page'] ?? 0));
        flash('success', 'Einstellungen gespeichert.');
        redirect('/admin/settings');
    }
}
