<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

/**
 * News-Beiträge und Events (Typ 'news' oder 'event') in einer Tabelle.
 */
class Post
{
    public static function allByType(string $type): array
    {
        $order = $type === 'event' ? 'start_at DESC' : 'COALESCE(published_at, created_at) DESC';
        $stmt = Database::pdo()->prepare("SELECT * FROM posts WHERE type = ? ORDER BY $order");
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM posts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findPublished(string $type, string $slug): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM posts WHERE type = ? AND slug = ? AND published = 1');
        $stmt->execute([$type, $slug]);
        return $stmt->fetch() ?: null;
    }

    public static function latestNews(int $limit): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM posts WHERE type = \'news\' AND published = 1
             AND (published_at IS NULL OR published_at <= NOW())
             ORDER BY COALESCE(published_at, created_at) DESC LIMIT ' . max(1, $limit)
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function upcomingEvents(int $limit): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM posts WHERE type = \'event\' AND published = 1
             AND COALESCE(end_at, start_at) >= NOW()
             ORDER BY start_at ASC LIMIT ' . max(1, $limit)
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO posts (type, title, slug, excerpt, body, image, published, published_at, start_at, end_at, location)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $data['type'], $data['title'], self::uniqueSlug($data['slug']),
            $data['excerpt'], $data['body'], $data['image'], $data['published'],
            $data['published_at'], $data['start_at'], $data['end_at'], $data['location'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::pdo()->prepare(
            'UPDATE posts SET title = ?, slug = ?, excerpt = ?, body = ?, image = ?, published = ?,
             published_at = ?, start_at = ?, end_at = ?, location = ? WHERE id = ?'
        )->execute([
            $data['title'], self::uniqueSlug($data['slug'], $id),
            $data['excerpt'], $data['body'], $data['image'], $data['published'],
            $data['published_at'], $data['start_at'], $data['end_at'], $data['location'], $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
    }

    private static function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $slug = $slug !== '' ? $slug : 'beitrag';
        $candidate = $slug;
        $i = 2;
        while (true) {
            $sql = 'SELECT id FROM posts WHERE slug = ?' . ($ignoreId ? ' AND id != ?' : '');
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($ignoreId ? [$candidate, $ignoreId] : [$candidate]);
            if (!$stmt->fetch()) {
                return $candidate;
            }
            $candidate = $slug . '-' . $i++;
        }
    }
}
