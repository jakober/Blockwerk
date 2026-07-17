<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class Page
{
    public static function all(): array
    {
        return Database::pdo()
            ->query('SELECT * FROM pages ORDER BY menu_order, title')
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM pages WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM pages WHERE slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function menuPages(): array
    {
        return Database::pdo()
            ->query('SELECT id, parent_id, title, slug FROM pages WHERE in_menu = 1 AND published = 1 ORDER BY menu_order, title')
            ->fetchAll();
    }

    /** Alle Seiten als flache Liste in Baum-Reihenfolge mit Tiefenangabe. */
    public static function tree(): array
    {
        $byParent = [];
        foreach (self::all() as $page) {
            $byParent[(int) ($page['parent_id'] ?? 0)][] = $page;
        }
        $result = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$result, $byParent): void {
            foreach ($byParent[$parentId] ?? [] as $page) {
                $page['depth'] = $depth;
                $result[] = $page;
                $walk((int) $page['id'], $depth + 1);
            }
        };
        $walk(0, 0);
        return $result;
    }

    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO pages (parent_id, title, slug, layout_id, in_menu, menu_order, published, content,
                                meta_title, meta_description, noindex)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['parent_id'], $data['title'], self::uniqueSlug($data['slug']),
            $data['layout_id'], $data['in_menu'], $data['menu_order'], $data['published'],
            $data['content'] ?? null,
            $data['meta_title'] ?? null, $data['meta_description'] ?? null, $data['noindex'] ?? 0,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE pages SET parent_id = ?, title = ?, slug = ?, layout_id = ?, in_menu = ?, menu_order = ?, published = ?,
                              meta_title = ?, meta_description = ?, noindex = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['parent_id'], $data['title'], self::uniqueSlug($data['slug'], $id),
            $data['layout_id'], $data['in_menu'], $data['menu_order'], $data['published'],
            $data['meta_title'] ?? null, $data['meta_description'] ?? null, $data['noindex'] ?? 0, $id,
        ]);
    }

    public static function saveContent(int $id, string $json): void
    {
        $stmt = Database::pdo()->prepare('UPDATE pages SET content = ? WHERE id = ?');
        $stmt->execute([$json, $id]);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        // Unterseiten eine Ebene nach oben schieben statt sie mitzulöschen.
        $stmt = $pdo->prepare('UPDATE pages SET parent_id = (SELECT parent_id FROM (SELECT parent_id FROM pages WHERE id = ?) t) WHERE parent_id = ?');
        $stmt->execute([$id, $id]);
        $pdo->prepare('DELETE FROM pages WHERE id = ?')->execute([$id]);
    }

    private static function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $slug = $slug !== '' ? $slug : 'seite';
        $candidate = $slug;
        $i = 2;
        while (true) {
            $sql = 'SELECT id FROM pages WHERE slug = ?' . ($ignoreId ? ' AND id != ?' : '');
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($ignoreId ? [$candidate, $ignoreId] : [$candidate]);
            if (!$stmt->fetch()) {
                return $candidate;
            }
            $candidate = $slug . '-' . $i++;
        }
    }
}
