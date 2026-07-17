<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\View;

class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/admin');
        }
        View::render('auth/login', ['flash' => flash()]);
    }

    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if (Auth::attempt($username, $password)) {
            redirect('/admin');
        }

        flash('error', 'Benutzername oder Passwort ist falsch.');
        redirect('/login');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/login');
    }
}
