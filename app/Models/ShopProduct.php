<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class ShopProduct
{
    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM shop_products ORDER BY position, name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_products WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_products WHERE slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function featured(int $limit = 8): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_products WHERE active = 1 AND featured = 1 ORDER BY position, name LIMIT ' . max(1, $limit));
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Produkte einer Kategorie (inkl. Unterkategorien) mit Filtern.
     * @param int[] $categoryIds  leer = alle
     */
    public static function query(array $categoryIds = [], array $opts = []): array
    {
        $where = ['active = 1'];
        $params = [];
        if ($categoryIds !== []) {
            $where[] = 'category_id IN (' . implode(',', array_fill(0, count($categoryIds), '?')) . ')';
            $params = array_merge($params, array_map('intval', $categoryIds));
        }
        if (($opts['search'] ?? '') !== '') {
            $where[] = '(name LIKE ? OR short_desc LIKE ? OR sku LIKE ?)';
            $like = '%' . $opts['search'] . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if (isset($opts['min']) && $opts['min'] !== '') {
            $where[] = 'price >= ?';
            $params[] = (int) $opts['min'];
        }
        if (isset($opts['max']) && $opts['max'] !== '') {
            $where[] = 'price <= ?';
            $params[] = (int) $opts['max'];
        }
        $order = match ($opts['sort'] ?? '') {
            'price_asc' => 'price ASC',
            'price_desc' => 'price DESC',
            'name' => 'name ASC',
            'newest' => 'created_at DESC',
            default => 'position ASC, name ASC',
        };
        $sql = 'SELECT * FROM shop_products WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function priceRange(): array
    {
        $row = Database::pdo()->query('SELECT MIN(price) AS lo, MAX(price) AS hi FROM shop_products WHERE active = 1')->fetch();
        return ['lo' => (int) ($row['lo'] ?? 0), 'hi' => (int) ($row['hi'] ?? 0)];
    }

    public static function create(array $d): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO shop_products (category_id, name, slug, sku, price, compare_price, description, short_desc, image, gallery, stock, weight, active, featured, position)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $d['category_id'] ?: null, $d['name'], self::uniqueSlug($d['slug'] ?: $d['name']), $d['sku'] ?? null,
                (int) $d['price'], $d['compare_price'] !== '' && $d['compare_price'] !== null ? (int) $d['compare_price'] : null,
                $d['description'] ?? null, $d['short_desc'] ?? null, $d['image'] ?? null, $d['gallery'] ?? null,
                $d['stock'] !== '' && $d['stock'] !== null ? (int) $d['stock'] : null,
                $d['weight'] !== '' && $d['weight'] !== null ? (int) $d['weight'] : null,
                (int) ($d['active'] ?? 1), (int) ($d['featured'] ?? 0), (int) ($d['position'] ?? 0),
            ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        Database::pdo()->prepare('UPDATE shop_products SET category_id = ?, name = ?, slug = ?, sku = ?, price = ?, compare_price = ?, description = ?, short_desc = ?, image = ?, gallery = ?, stock = ?, weight = ?, active = ?, featured = ?, position = ? WHERE id = ?')
            ->execute([
                $d['category_id'] ?: null, $d['name'], self::uniqueSlug($d['slug'] ?: $d['name'], $id), $d['sku'] ?? null,
                (int) $d['price'], $d['compare_price'] !== '' && $d['compare_price'] !== null ? (int) $d['compare_price'] : null,
                $d['description'] ?? null, $d['short_desc'] ?? null, $d['image'] ?? null, $d['gallery'] ?? null,
                $d['stock'] !== '' && $d['stock'] !== null ? (int) $d['stock'] : null,
                $d['weight'] !== '' && $d['weight'] !== null ? (int) $d['weight'] : null,
                (int) ($d['active'] ?? 1), (int) ($d['featured'] ?? 0), (int) ($d['position'] ?? 0), $id,
            ]);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM shop_products WHERE id = ?')->execute([$id]);
    }

    public static function decreaseStock(int $id, int $qty): void
    {
        Database::pdo()->prepare('UPDATE shop_products SET stock = GREATEST(0, stock - ?) WHERE id = ? AND stock IS NOT NULL')->execute([$qty, $id]);
    }

    private static function uniqueSlug(string $slug, ?int $ignore = null): string
    {
        $slug = slugify($slug) ?: 'produkt';
        $candidate = $slug;
        $i = 2;
        while (true) {
            $sql = 'SELECT id FROM shop_products WHERE slug = ?' . ($ignore ? ' AND id != ?' : '');
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($ignore ? [$candidate, $ignore] : [$candidate]);
            if (!$stmt->fetch()) {
                return $candidate;
            }
            $candidate = $slug . '-' . $i++;
        }
    }
}
