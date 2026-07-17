<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

/** Versionsverlauf des Seiteninhalts – bei jedem Speichern im Editor. */
class PageVersion
{
    private const KEEP = 20;

    public static function forPage(int $pageId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, title, username, created_at FROM page_versions WHERE page_id = ? ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$pageId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM page_versions WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function add(int $pageId, string $title, ?string $content, ?string $username): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO page_versions (page_id, title, content, username) VALUES (?, ?, ?, ?)')
            ->execute([$pageId, $title, $content, $username]);

        // Nur die letzten Versionen behalten.
        $stmt = $pdo->prepare(
            'SELECT id FROM page_versions WHERE page_id = ? ORDER BY created_at DESC, id DESC LIMIT 100 OFFSET ' . self::KEEP
        );
        $stmt->execute([$pageId]);
        $old = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if ($old !== []) {
            $pdo->exec('DELETE FROM page_versions WHERE id IN (' . implode(',', array_map('intval', $old)) . ')');
        }
    }
}
