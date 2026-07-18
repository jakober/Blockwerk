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

    /** Anzeigename, Alt-Text, Titel und Ordner ändern (Datei/URL bleibt gleich). */
    public static function updateMeta(int $id, string $filename, ?string $alt, ?string $title, ?int $folderId): void
    {
        Database::pdo()->prepare('UPDATE media SET filename = ?, alt = ?, title = ?, folder_id = ? WHERE id = ?')
            ->execute([$filename, $alt, $title, $folderId, $id]);
    }

    public static function setFolder(int $id, ?int $folderId): void
    {
        Database::pdo()->prepare('UPDATE media SET folder_id = ? WHERE id = ?')->execute([$folderId, $id]);
    }

    /** Suche über Name/Alt/Titel, optional auf einen Ordner begrenzt. */
    public static function search(string $query = '', ?int $folderId = null, int $limit = 100): array
    {
        $sql = 'SELECT * FROM media WHERE 1=1';
        $params = [];
        if ($query !== '') {
            $sql .= ' AND (filename LIKE ? OR alt LIKE ? OR title LIKE ?)';
            $like = '%' . $query . '%';
            $params = [$like, $like, $like];
        }
        if ($folderId !== null) {
            $sql .= ' AND folder_id = ?';
            $params[] = $folderId;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(500, $limit));
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
