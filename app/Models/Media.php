<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class Media
{
    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM media ORDER BY created_at DESC, id DESC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM media WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $filename, string $path, string $mime, int $size, ?int $width, ?int $height): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO media (filename, path, mime, size, width, height) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$filename, $path, $mime, $size, $width, $height]);
        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);
    }
}
