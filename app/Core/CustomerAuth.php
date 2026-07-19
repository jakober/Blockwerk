<?php
declare(strict_types=1);

namespace Core;

use Models\Customer;

/**
 * Anmeldung für Shop-Kunden – bewusst getrennt von der Admin-Anmeldung
 * (Core\Auth). Eigene Session-Keys (customer_id / customer_email), damit sich
 * Admin- und Kunden-Login nicht überschneiden.
 */
class CustomerAuth
{
    public static function check(): bool
    {
        return isset($_SESSION['customer_id']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : null;
    }

    public static function email(): string
    {
        return (string) ($_SESSION['customer_email'] ?? '');
    }

    public static function current(): ?array
    {
        return self::check() ? Customer::find((int) $_SESSION['customer_id']) : null;
    }

    public static function attempt(string $email, string $password): bool
    {
        $customer = Customer::findByEmail($email);
        if ($customer === null || !password_verify($password, $customer['password_hash'])) {
            return false;
        }
        self::login($customer);
        return true;
    }

    /** Meldet ein (bereits geladenes) Kundenkonto an. */
    public static function login(array $customer): void
    {
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int) $customer['id'];
        $_SESSION['customer_email'] = (string) $customer['email'];
    }

    public static function logout(): void
    {
        unset($_SESSION['customer_id'], $_SESSION['customer_email']);
        session_regenerate_id(true);
    }

    /** Nicht eingeloggt → zur Shop-Anmeldung. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            flash('error', 'Bitte melde dich an, um dein Konto zu sehen.');
            redirect('/' . trim(Shop::rootSlug(), '/') . '/login');
        }
    }
}
