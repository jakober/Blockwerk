<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

/** Ordner der Mediathek (flache Struktur). */
class MediaFolder
{
    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM media_folders ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM media_folders WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByName(string $name): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM media_folders WHERE LOWER(name) = LOWER(?)');
        $stmt->execute([$name]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO media_folders (name) VALUES (?)')->execute([$name]);
        return (int) $pdo->lastInsertId();
    }

    public static function rename(int $id, string $name): void
    {
        Database::pdo()->prepare('UPDATE media_folders SET name = ? WHERE id = ?')->execute([$name, $id]);
    }

    /** Ordner löschen – enthaltene Dateien wandern zurück in „Alle Dateien". */
    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE media SET folder_id = NULL WHERE folder_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM media_folders WHERE id = ?')->execute([$id]);
    }
}
