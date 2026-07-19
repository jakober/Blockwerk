<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

/**
 * Shop-Kundenkonten – getrennt von den Admin-Benutzern (Tabelle shop_customers).
 * Passwörter als password_hash (PASSWORD_DEFAULT).
 */
class Customer
{
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_customers WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_customers WHERE email = ?');
        $stmt->execute([mb_strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public static function emailExists(string $email): bool
    {
        return self::findByEmail($email) !== null;
    }

    /** Legt ein Konto an und liefert die neue ID. */
    public static function create(string $email, string $password, string $firstName = '', string $lastName = ''): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO shop_customers (email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?)')
            ->execute([
                mb_strtolower(trim($email)),
                password_hash($password, PASSWORD_DEFAULT),
                $firstName !== '' ? $firstName : null,
                $lastName !== '' ? $lastName : null,
            ]);
        return (int) $pdo->lastInsertId();
    }

    public static function updateName(int $id, string $firstName, string $lastName): void
    {
        Database::pdo()->prepare('UPDATE shop_customers SET first_name = ?, last_name = ? WHERE id = ?')
            ->execute([$firstName ?: null, $lastName ?: null, $id]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        Database::pdo()->prepare('UPDATE shop_customers SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    /** Setzt einen Reset-Token (gültig 1 Stunde) und gibt ihn zurück. */
    public static function setResetToken(int $id): string
    {
        $token = bin2hex(random_bytes(24));
        Database::pdo()->prepare('UPDATE shop_customers SET reset_token = ?, reset_expires = ? WHERE id = ?')
            ->execute([$token, date('Y-m-d H:i:s', time() + 3600), $id]);
        return $token;
    }

    public static function findByValidResetToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_customers WHERE reset_token = ? AND reset_expires > ?');
        $stmt->execute([$token, date('Y-m-d H:i:s')]);
        return $stmt->fetch() ?: null;
    }
}
