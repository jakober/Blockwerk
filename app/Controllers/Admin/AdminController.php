<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Auth;
use Core\Updater;
use Core\View;

abstract class AdminController
{
    public function __construct()
    {
        Auth::requireLogin();
        // Beim Betreten des Backends (normale Seitenaufrufe) prüfen, ob es ein
        // Update gibt – gecacht (höchstens alle 6 h) und mit kurzem Timeout.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            try {
                Updater::refreshIfStale();
            } catch (\Throwable) {
            }
        }
    }

    protected function view(string $template, array $data = []): void
    {
        $data['flash'] ??= flash();
        View::render($template, $data, 'admin/_shell');
    }

    /** Nur für Administratoren – Redakteure werden zum Dashboard umgeleitet. */
    protected function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            flash('error', 'Dafür brauchst du Administrator-Rechte.');
            redirect('/admin');
        }
    }
}
