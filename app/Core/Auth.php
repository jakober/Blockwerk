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
        return User::find((int) $_SESSION['user_id']);
    }

    public static function attempt(string $username, string $password): bool
    {
        $user = User::findByUsername($username);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['username']);
        session_regenerate_id(true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/login');
        }
    }
}
