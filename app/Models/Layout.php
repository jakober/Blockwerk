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

    public static function create(string $name, string $html, ?string $design = null, string $headCode = '', string $bodyCode = ''): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO layouts (name, html, design, head_code, body_code) VALUES (?, ?, ?, ?, ?)')
            ->execute([$name, $html, $design, $headCode, $bodyCode]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $name, string $html, ?string $design = null, string $headCode = '', string $bodyCode = ''): void
    {
        Database::pdo()->prepare('UPDATE layouts SET name = ?, html = ?, design = ?, head_code = ?, body_code = ? WHERE id = ?')
            ->execute([$name, $html, $design, $headCode, $bodyCode, $id]);
    }

    public static function saveBuilder(int $id, ?string $json): void
    {
        Database::pdo()->prepare('UPDATE layouts SET builder = ? WHERE id = ?')->execute([$json, $id]);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE pages SET layout_id = NULL WHERE layout_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM layouts WHERE id = ?')->execute([$id]);
    }
}
