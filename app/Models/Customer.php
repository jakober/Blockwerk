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
    /**
     * Stellt die Tabelle shop_customers sicher (Selbstheilung, falls die
     * Update-Migration nicht durchlief) – einmal pro Request.
     */
    private static function ensureTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            Database::pdo()->exec('CREATE TABLE IF NOT EXISTS shop_customers (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                first_name VARCHAR(120) NULL,
                last_name VARCHAR(120) NULL,
                reset_token VARCHAR(64) NULL,
                reset_expires DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        } catch (\Throwable) {
        }
    }

    public static function find(int $id): ?array
    {
        self::ensureTable();
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_customers WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        self::ensureTable();
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_customers WHERE email = ?');
        $stmt->execute([mb_strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public static function emailExists(string $email): bool
    {
        return self::findByEmail($email) !== null;
    }

    /** Alle Kunden mit Anzahl ihrer Bestellungen (für die Backend-Verwaltung). */
    public static function all(): array
    {
        self::ensureTable();
        $pdo = Database::pdo();
        // Bestellungen per customer_id ODER E-Mail (Gastbestellungen) zählen –
        // mit Rückfall, falls die Spalte customer_id (noch) fehlt.
        $withId = 'SELECT c.*, (SELECT COUNT(*) FROM shop_orders o WHERE o.customer_id = c.id OR LOWER(o.email) = LOWER(c.email)) AS order_count
                   FROM shop_customers c ORDER BY c.created_at DESC';
        $byMail = 'SELECT c.*, (SELECT COUNT(*) FROM shop_orders o WHERE LOWER(o.email) = LOWER(c.email)) AS order_count
                   FROM shop_customers c ORDER BY c.created_at DESC';
        foreach ([$withId, $byMail, 'SELECT c.*, 0 AS order_count FROM shop_customers c ORDER BY c.created_at DESC'] as $sql) {
            try {
                return $pdo->query($sql)->fetchAll();
            } catch (\Throwable) {
            }
        }
        return [];
    }

    public static function update(int $id, string $email, string $firstName, string $lastName): void
    {
        Database::pdo()->prepare('UPDATE shop_customers SET email = ?, first_name = ?, last_name = ? WHERE id = ?')
            ->execute([mb_strtolower(trim($email)), $firstName ?: null, $lastName ?: null, $id]);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        // Bestellungen bleiben erhalten, verlieren nur die Konto-Zuordnung.
        try {
            $pdo->prepare('UPDATE shop_orders SET customer_id = NULL WHERE customer_id = ?')->execute([$id]);
        } catch (\Throwable) {
        }
        $pdo->prepare('DELETE FROM shop_customers WHERE id = ?')->execute([$id]);
    }

    /** Legt ein Konto an und liefert die neue ID. */
    public static function create(string $email, string $password, string $firstName = '', string $lastName = ''): int
    {
        self::ensureTable();
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
