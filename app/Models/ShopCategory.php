<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class ShopCategory
{
    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM shop_categories ORDER BY position, name')->fetchAll();
    }

    /** Flache Liste in Baum-Reihenfolge mit Tiefenangabe. */
    public static function tree(): array
    {
        $byParent = [];
        foreach (self::all() as $cat) {
            $byParent[(int) ($cat['parent_id'] ?? 0)][] = $cat;
        }
        $out = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$out, $byParent): void {
            foreach ($byParent[$parent] ?? [] as $cat) {
                $cat['depth'] = $depth;
                $out[] = $cat;
                $walk((int) $cat['id'], $depth + 1);
            }
        };
        $walk(0, 0);
        return $out;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_categories WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_categories WHERE slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /** Ids der Kategorie inkl. aller Unterkategorien. */
    public static function withDescendants(int $id): array
    {
        $ids = [$id];
        $byParent = [];
        foreach (self::all() as $cat) {
            $byParent[(int) ($cat['parent_id'] ?? 0)][] = (int) $cat['id'];
        }
        $stack = [$id];
        while ($stack) {
            $current = array_pop($stack);
            foreach ($byParent[$current] ?? [] as $child) {
                $ids[] = $child;
                $stack[] = $child;
            }
        }
        return array_values(array_unique($ids));
    }

    public static function create(array $d): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO shop_categories (parent_id, name, slug, description, image, position) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$d['parent_id'] ?: null, $d['name'], self::uniqueSlug($d['slug'] ?: $d['name']), $d['description'] ?? null, $d['image'] ?? null, (int) ($d['position'] ?? 0)]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        Database::pdo()->prepare('UPDATE shop_categories SET parent_id = ?, name = ?, slug = ?, description = ?, image = ?, position = ? WHERE id = ?')
            ->execute([$d['parent_id'] ?: null, $d['name'], self::uniqueSlug($d['slug'] ?: $d['name'], $id), $d['description'] ?? null, $d['image'] ?? null, (int) ($d['position'] ?? 0), $id]);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        // Unterkategorien und Produkte lösen sich (keine harte Löschung der Produkte).
        $pdo->prepare('UPDATE shop_categories SET parent_id = NULL WHERE parent_id = ?')->execute([$id]);
        $pdo->prepare('UPDATE shop_products SET category_id = NULL WHERE category_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM shop_categories WHERE id = ?')->execute([$id]);
    }

    public static function productCount(int $id): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM shop_products WHERE category_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    private static function uniqueSlug(string $slug, ?int $ignore = null): string
    {
        $slug = slugify($slug) ?: 'kategorie';
        $candidate = $slug;
        $i = 2;
        while (true) {
            $sql = 'SELECT id FROM shop_categories WHERE slug = ?' . ($ignore ? ' AND id != ?' : '');
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($ignore ? [$candidate, $ignore] : [$candidate]);
            if (!$stmt->fetch()) {
                return $candidate;
            }
            $candidate = $slug . '-' . $i++;
        }
    }
}
