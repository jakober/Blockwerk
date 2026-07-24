<?php
declare(strict_types=1);

namespace Core;

use Models\User;

class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        // KI-Modus: Admin kommt aus config.php (keine Datenbank).
        if (Config::mode() === 'ai') {
            return ['id' => 0, 'username' => (string) ($_SESSION['username'] ?? 'admin'), 'role' => 'admin'];
        }
        return User::find((int) $_SESSION['user_id']);
    }

    public static function attempt(string $username, string $password): bool
    {
        // KI-Modus: gegen die dateibasierten Zugangsdaten prüfen.
        if (Config::mode() === 'ai') {
            $u = (string) Config::sub('ai', 'admin_user', '');
            $h = (string) Config::sub('ai', 'admin_pass_hash', '');
            if ($u !== '' && $h !== '' && hash_equals($u, $username) && password_verify($password, $h)) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = 'ai-admin';
                $_SESSION['username'] = $u;
                $_SESSION['role'] = 'admin';
                return true;
            }
            return false;
        }
        $user = User::findByUsername($username);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'] ?? 'admin';
        return true;
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['role'] ?? 'admin') === 'admin';
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
        session_regenerate_id(true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/login');
        }
    }
}
