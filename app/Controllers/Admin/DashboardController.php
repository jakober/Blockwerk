<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Database;

class DashboardController extends AdminController
{
    public function index(): void
    {
        $pdo = Database::pdo();
        $counts = [
            'pages' => (int) $pdo->query('SELECT COUNT(*) FROM pages')->fetchColumn(),
            'news' => (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE type = 'news'")->fetchColumn(),
            'events' => (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE type = 'event'")->fetchColumn(),
            'media' => (int) $pdo->query('SELECT COUNT(*) FROM media')->fetchColumn(),
            'layouts' => (int) $pdo->query('SELECT COUNT(*) FROM layouts')->fetchColumn(),
            'templates' => (int) $pdo->query('SELECT COUNT(*) FROM templates')->fetchColumn(),
        ];

        if (\Core\Shop::enabled()) {
            $counts['shop_products'] = (int) $pdo->query('SELECT COUNT(*) FROM shop_products')->fetchColumn();
            $counts['shop_orders'] = (int) $pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn();
        }

        $this->view('admin/dashboard', [
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'counts' => $counts,
            'currentVersion' => \Core\Updater::currentVersion(),
            'updateVersion' => \Core\Auth::isAdmin() ? \Core\Updater::updateAvailable() : null,
        ]);
    }
}
