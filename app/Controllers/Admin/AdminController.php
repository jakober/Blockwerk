<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Auth;
use Core\View;

abstract class AdminController
{
    public function __construct()
    {
        Auth::requireLogin();
        // Die Update-Prüfung läuft NICHT hier (das würde den Seitenaufbau
        // blockieren), sondern per Hintergrund-XHR nach dem Laden der Seite –
        // siehe /admin/update/status (UpdateController::status).
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
