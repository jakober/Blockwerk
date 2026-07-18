<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class ShopShipping
{
    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM shop_shipping ORDER BY position, name')->fetchAll();
    }

    public static function active(): array
    {
        return Database::pdo()->query('SELECT * FROM shop_shipping WHERE active = 1 ORDER BY position, name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_shipping WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $d): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO shop_shipping (name, description, price, free_from, active, position) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([
                $d['name'], $d['description'] ?? null, (int) $d['price'],
                $d['free_from'] !== '' && $d['free_from'] !== null ? (int) $d['free_from'] : null,
                (int) ($d['active'] ?? 1), (int) ($d['position'] ?? 0),
            ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        Database::pdo()->prepare('UPDATE shop_shipping SET name = ?, description = ?, price = ?, free_from = ?, active = ?, position = ? WHERE id = ?')
            ->execute([
                $d['name'], $d['description'] ?? null, (int) $d['price'],
                $d['free_from'] !== '' && $d['free_from'] !== null ? (int) $d['free_from'] : null,
                (int) ($d['active'] ?? 1), (int) ($d['position'] ?? 0), $id,
            ]);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM shop_shipping WHERE id = ?')->execute([$id]);
    }

    /** Versandkosten für einen Warenkorb-Zwischensumme (Gratis-ab beachten). */
    public static function costFor(array $method, int $subtotal): int
    {
        $freeFrom = $method['free_from'] ?? null;
        if ($freeFrom !== null && $freeFrom !== '' && $subtotal >= (int) $freeFrom) {
            return 0;
        }
        return (int) $method['price'];
    }
}
