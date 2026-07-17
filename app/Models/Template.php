<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class Template
{
    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM templates ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM templates WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByKey(string $key): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM templates WHERE tkey = ?');
        $stmt->execute([$key]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name, string $key, string $html): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO templates (name, tkey, html) VALUES (?, ?, ?)')->execute([$name, $key, $html]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $name, string $key, string $html): void
    {
        Database::pdo()->prepare('UPDATE templates SET name = ?, tkey = ?, html = ? WHERE id = ?')
            ->execute([$name, $key, $html, $id]);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM templates WHERE id = ?')->execute([$id]);
    }
}
