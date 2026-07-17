<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class Page
{
    private const LIVE = 'deleted_at IS NULL AND is_global = 0';

    public static function all(): array
    {
        return Database::pdo()
            ->query('SELECT * FROM pages WHERE ' . self::LIVE . ' ORDER BY menu_order, title')
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
        $stmt = Database::pdo()->prepare('SELECT * FROM pages WHERE slug = ? AND ' . self::LIVE);
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function menuPages(string $lang): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, parent_id, title, slug, lang FROM pages
             WHERE in_menu = 1 AND published = 1 AND lang = ? AND ' . self::LIVE . '
             ORDER BY menu_order, title'
        );
        $stmt->execute([$lang]);
        return $stmt->fetchAll();
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

    /* ---------- Papierkorb ---------- */

    public static function trashed(): array
    {
        return Database::pdo()
            ->query('SELECT * FROM pages WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC')
            ->fetchAll();
    }

    public static function softDelete(int $id): void
    {
        Database::pdo()->prepare('UPDATE pages SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
    }

    public static function restore(int $id): void
    {
        Database::pdo()->prepare('UPDATE pages SET deleted_at = NULL WHERE id = ?')->execute([$id]);
    }

    /** Endgültig löschen; Unterseiten rücken eine Ebene nach oben. */
    public static function destroy(int $id): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE pages SET parent_id = (SELECT parent_id FROM (SELECT parent_id FROM pages WHERE id = ?) t) WHERE parent_id = ?');
        $stmt->execute([$id, $id]);
        $pdo->prepare('DELETE FROM page_versions WHERE page_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM pages WHERE id = ?')->execute([$id]);
    }

    /* ---------- Globale Blöcke (als Spezial-Seiten) ---------- */

    public static function globals(): array
    {
        return Database::pdo()
            ->query('SELECT * FROM pages WHERE is_global = 1 AND deleted_at IS NULL ORDER BY title')
            ->fetchAll();
    }

    /* ---------- CRUD ---------- */

    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO pages (parent_id, title, slug, layout_id, in_menu, menu_order, published, content,
                                meta_title, meta_description, noindex, lang, is_global)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['parent_id'], $data['title'], self::uniqueSlug($data['slug']),
            $data['layout_id'], $data['in_menu'], $data['menu_order'], $data['published'],
            $data['content'] ?? null,
            $data['meta_title'] ?? null, $data['meta_description'] ?? null, $data['noindex'] ?? 0,
            $data['lang'] ?? 'de', $data['is_global'] ?? 0,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE pages SET parent_id = ?, title = ?, slug = ?, layout_id = ?, in_menu = ?, menu_order = ?, published = ?,
                              meta_title = ?, meta_description = ?, noindex = ?, lang = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['parent_id'], $data['title'], self::uniqueSlug($data['slug'], $id),
            $data['layout_id'], $data['in_menu'], $data['menu_order'], $data['published'],
            $data['meta_title'] ?? null, $data['meta_description'] ?? null, $data['noindex'] ?? 0,
            $data['lang'] ?? 'de', $id,
        ]);
    }

    public static function duplicate(int $id): ?int
    {
        $page = self::find($id);
        if ($page === null) {
            return null;
        }
        return self::create([
            'parent_id' => $page['parent_id'],
            'title' => $page['title'] . ' (Kopie)',
            'slug' => $page['slug'] . '-kopie',
            'layout_id' => $page['layout_id'],
            'in_menu' => 0,
            'menu_order' => (int) $page['menu_order'],
            'published' => 0,
            'content' => $page['content'],
            'meta_title' => $page['meta_title'] ?? null,
            'meta_description' => $page['meta_description'] ?? null,
            'noindex' => (int) ($page['noindex'] ?? 0),
            'lang' => $page['lang'] ?? 'de',
            'is_global' => (int) ($page['is_global'] ?? 0),
        ]);
    }

    public static function saveContent(int $id, string $json): void
    {
        $stmt = Database::pdo()->prepare('UPDATE pages SET content = ? WHERE id = ?');
        $stmt->execute([$json, $id]);
    }

    /* ---------- Suche ---------- */

    public static function search(string $query, int $limit = 20): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM pages
             WHERE published = 1 AND noindex = 0 AND ' . self::LIVE . '
             AND (title LIKE ? OR content LIKE ? OR meta_description LIKE ?)
             ORDER BY title LIMIT ' . max(1, $limit)
        );
        $like = '%' . $query . '%';
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll();
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
