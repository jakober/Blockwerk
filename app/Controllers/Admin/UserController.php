<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Models\User;

class UserController extends AdminController
{
    public function index(): void
    {
        $this->requireAdmin();
        $this->view('admin/users/index', [
            'title' => 'Benutzer',
            'active' => 'users',
            'users' => User::all(),
            'ownId' => (int) ($_SESSION['user_id'] ?? 0),
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();
        $this->form(null);
    }

    public function edit(string $id): void
    {
        // Redakteure dürfen nur das eigene Profil bearbeiten.
        if (!\Core\Auth::isAdmin() && (int) $id !== (int) ($_SESSION['user_id'] ?? 0)) {
            $this->requireAdmin();
        }
        $user = User::find((int) $id) ?? $this->abort();
        $this->form($user);
    }

    public function store(): void
    {
        $this->requireAdmin();
        $username = $this->validUsername('/admin/users/new');
        $password = $this->validPassword('/admin/users/new', required: true);
        if (User::findByUsername($username) !== null) {
            flash('error', 'Der Benutzername "' . $username . '" ist bereits vergeben.');
            redirect('/admin/users/new');
        }
        User::createUser($username, $password);
        $this->applyRole((int) \Core\Database::pdo()->lastInsertId() ?: 0, $username);
        flash('success', 'Benutzer "' . $username . '" angelegt.');
        redirect('/admin/users');
    }

    public function update(string $id): void
    {
        if (!\Core\Auth::isAdmin() && (int) $id !== (int) ($_SESSION['user_id'] ?? 0)) {
            $this->requireAdmin();
        }
        $user = User::find((int) $id) ?? $this->abort();
        $back = '/admin/users/' . $user['id'] . '/edit';

        $username = $this->validUsername($back);
        $existing = User::findByUsername($username);
        if ($existing !== null && (int) $existing['id'] !== (int) $user['id']) {
            flash('error', 'Der Benutzername "' . $username . '" ist bereits vergeben.');
            redirect($back);
        }
        User::updateName((int) $user['id'], $username);

        // Passwort nur ändern, wenn eines eingegeben wurde.
        $password = $this->validPassword($back, required: false);
        if ($password !== null) {
            User::updatePassword((int) $user['id'], $password);
        }

        $this->applyRole((int) $user['id'], $username);

        if ((int) $user['id'] === (int) ($_SESSION['user_id'] ?? 0)) {
            $_SESSION['username'] = $username;
        }

        flash('success', 'Benutzer gespeichert.' . ($password !== null ? ' Das neue Passwort gilt ab der nächsten Anmeldung.' : ''));
        redirect(\Core\Auth::isAdmin() ? '/admin/users' : '/admin');
    }

    /** Rolle setzen – nur durch Admins, nie für das eigene Konto, nie den letzten Admin entfernen. */
    private function applyRole(int $userId, string $username): void
    {
        if (!\Core\Auth::isAdmin() || !isset($_POST['role']) || $userId <= 0) {
            return;
        }
        $role = $_POST['role'] === 'editor' ? 'editor' : 'admin';
        if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
            return; // Eigene Rolle nicht ändern (Aussperr-Schutz).
        }
        if ($role === 'editor') {
            $admins = (int) \Core\Database::pdo()
                ->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != " . $userId)->fetchColumn();
            if ($admins < 1) {
                flash('error', 'Der letzte Administrator kann nicht zum Redakteur gemacht werden.');
                return;
            }
        }
        \Core\Database::pdo()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $userId]);
    }

    public function delete(string $id): void
    {
        $this->requireAdmin();
        $user = User::find((int) $id) ?? $this->abort();
        if ((int) $user['id'] === (int) ($_SESSION['user_id'] ?? 0)) {
            flash('error', 'Du kannst dein eigenes Konto nicht löschen.');
            redirect('/admin/users');
        }
        if (User::count() <= 1) {
            flash('error', 'Der letzte Benutzer kann nicht gelöscht werden.');
            redirect('/admin/users');
        }
        User::delete((int) $user['id']);
        flash('success', 'Benutzer "' . $user['username'] . '" gelöscht.');
        redirect('/admin/users');
    }

    private function form(?array $user): void
    {
        $this->view('admin/users/form', [
            'title' => $user ? 'Benutzer bearbeiten' : 'Neuer Benutzer',
            'active' => 'users',
            'user' => $user,
            'isSelf' => $user !== null && (int) $user['id'] === (int) ($_SESSION['user_id'] ?? 0),
        ]);
    }

    private function validUsername(string $backTo): string
    {
        $username = trim($_POST['username'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9._\-]{3,64}$/', $username)) {
            flash('error', 'Der Benutzername braucht 3–64 Zeichen (Buchstaben, Zahlen, Punkt, Unterstrich, Bindestrich).');
            redirect($backTo);
        }
        return $username;
    }

    /** Gibt das neue Passwort zurück oder null, wenn keines gesetzt werden soll. */
    private function validPassword(string $backTo, bool $required): ?string
    {
        $password = (string) ($_POST['password'] ?? '');
        $repeat = (string) ($_POST['password_repeat'] ?? '');
        if ($password === '' && $repeat === '' && !$required) {
            return null;
        }
        if (strlen($password) < 8) {
            flash('error', 'Das Passwort braucht mindestens 8 Zeichen.');
            redirect($backTo);
        }
        if ($password !== $repeat) {
            flash('error', 'Die Passwörter stimmen nicht überein.');
            redirect($backTo);
        }
        return $password;
    }

    private function abort(): never
    {
        flash('error', 'Benutzer nicht gefunden.');
        redirect('/admin/users');
    }
}
