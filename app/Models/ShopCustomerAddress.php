<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

/** Adressbuch eines Kundenkontos – mehrere Lieferadressen, je eine als Standard markierbar. */
class ShopCustomerAddress
{
    public static function forCustomer(int $customerId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_customer_addresses WHERE customer_id = ? ORDER BY id DESC');
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id, int $customerId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_customer_addresses WHERE id = ? AND customer_id = ?');
        $stmt->execute([$id, $customerId]);
        return $stmt->fetch() ?: null;
    }

    public static function defaultShipping(int $customerId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_customer_addresses WHERE customer_id = ? AND is_default_shipping = 1 LIMIT 1');
        $stmt->execute([$customerId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $customerId, array $d): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO shop_customer_addresses (customer_id, label, first_name, last_name, company, street, zip, city, country, phone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $customerId, $d['label'] ?: null, $d['first_name'] ?: null, $d['last_name'] ?: null, $d['company'] ?: null,
                $d['street'] ?: null, $d['zip'] ?: null, $d['city'] ?: null, $d['country'] ?: null, $d['phone'] ?: null,
            ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, int $customerId, array $d): void
    {
        Database::pdo()->prepare('UPDATE shop_customer_addresses SET label = ?, first_name = ?, last_name = ?, company = ?, street = ?, zip = ?, city = ?, country = ?, phone = ?
            WHERE id = ? AND customer_id = ?')
            ->execute([
                $d['label'] ?: null, $d['first_name'] ?: null, $d['last_name'] ?: null, $d['company'] ?: null,
                $d['street'] ?: null, $d['zip'] ?: null, $d['city'] ?: null, $d['country'] ?: null, $d['phone'] ?: null,
                $id, $customerId,
            ]);
    }

    public static function delete(int $id, int $customerId): void
    {
        Database::pdo()->prepare('DELETE FROM shop_customer_addresses WHERE id = ? AND customer_id = ?')->execute([$id, $customerId]);
    }

    /** Genau eine Adresse als Standard (Lieferung oder Rechnung) markieren. */
    public static function setDefault(int $id, int $customerId, string $type): void
    {
        $col = $type === 'billing' ? 'is_default_billing' : 'is_default_shipping';
        $pdo = Database::pdo();
        $pdo->prepare("UPDATE shop_customer_addresses SET {$col} = 0 WHERE customer_id = ?")->execute([$customerId]);
        $pdo->prepare("UPDATE shop_customer_addresses SET {$col} = 1 WHERE id = ? AND customer_id = ?")->execute([$id, $customerId]);
    }
}
