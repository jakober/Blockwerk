<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class Layout
{
    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM layouts ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM layouts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function first(): ?array
    {
        return Database::pdo()->query('SELECT * FROM layouts ORDER BY id LIMIT 1')->fetch() ?: null;
    }

    public static function create(string $name, string $html): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO layouts (name, html) VALUES (?, ?)')->execute([$name, $html]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $name, string $html): void
    {
        Database::pdo()->prepare('UPDATE layouts SET name = ?, html = ? WHERE id = ?')->execute([$name, $html, $id]);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE pages SET layout_id = NULL WHERE layout_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM layouts WHERE id = ?')->execute([$id]);
    }
}
