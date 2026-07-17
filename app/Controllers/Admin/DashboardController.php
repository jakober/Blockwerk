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
            'layouts' => (int) $pdo->query('SELECT COUNT(*) FROM layouts')->fetchColumn(),
            'templates' => (int) $pdo->query('SELECT COUNT(*) FROM templates')->fetchColumn(),
        ];

        $this->view('admin/dashboard', [
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'counts' => $counts,
        ]);
    }
}
