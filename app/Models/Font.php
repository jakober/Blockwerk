<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class Font
{
    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM fonts ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM fonts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name, string $family, string $folder): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO fonts (name, family, folder) VALUES (?, ?, ?)')
            ->execute([$name, $family, $folder]);
        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM fonts WHERE id = ?')->execute([$id]);
    }
}
