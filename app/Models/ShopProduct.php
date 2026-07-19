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
        $pdo->prepare('INSERT INTO shop_products (category_id, name, slug, sku, price, compare_price, description, short_desc, image, gallery, tier_prices, options, cross_sell, accessories, stock, weight, active, featured, position)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $d['category_id'] ?: null, $d['name'], self::uniqueSlug($d['slug'] ?: $d['name']), $d['sku'] ?? null,
                (int) $d['price'], $d['compare_price'] !== '' && $d['compare_price'] !== null ? (int) $d['compare_price'] : null,
                $d['description'] ?? null, $d['short_desc'] ?? null, $d['image'] ?? null, $d['gallery'] ?? null,
                $d['tier_prices'] ?? null, $d['options'] ?? null, $d['cross_sell'] ?? null, $d['accessories'] ?? null,
                $d['stock'] !== '' && $d['stock'] !== null ? (int) $d['stock'] : null,
                $d['weight'] !== '' && $d['weight'] !== null ? (int) $d['weight'] : null,
                (int) ($d['active'] ?? 1), (int) ($d['featured'] ?? 0), (int) ($d['position'] ?? 0),
            ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        Database::pdo()->prepare('UPDATE shop_products SET category_id = ?, name = ?, slug = ?, sku = ?, price = ?, compare_price = ?, description = ?, short_desc = ?, image = ?, gallery = ?, tier_prices = ?, options = ?, cross_sell = ?, accessories = ?, stock = ?, weight = ?, active = ?, featured = ?, position = ? WHERE id = ?')
            ->execute([
                $d['category_id'] ?: null, $d['name'], self::uniqueSlug($d['slug'] ?: $d['name'], $id), $d['sku'] ?? null,
                (int) $d['price'], $d['compare_price'] !== '' && $d['compare_price'] !== null ? (int) $d['compare_price'] : null,
                $d['description'] ?? null, $d['short_desc'] ?? null, $d['image'] ?? null, $d['gallery'] ?? null,
                $d['tier_prices'] ?? null, $d['options'] ?? null, $d['cross_sell'] ?? null, $d['accessories'] ?? null,
                $d['stock'] !== '' && $d['stock'] !== null ? (int) $d['stock'] : null,
                $d['weight'] !== '' && $d['weight'] !== null ? (int) $d['weight'] : null,
                (int) ($d['active'] ?? 1), (int) ($d['featured'] ?? 0), (int) ($d['position'] ?? 0), $id,
            ]);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM shop_products WHERE id = ?')->execute([$id]);
    }

    /* ---------- Staffelpreise, Varianten, Cross-Selling ---------- */

    /** @return array<int,array{min:int,price:int}> aufsteigend nach Menge */
    public static function tiers(array $product): array
    {
        $raw = json_decode((string) ($product['tier_prices'] ?? ''), true);
        if (!is_array($raw)) {
            return [];
        }
        $tiers = [];
        foreach ($raw as $t) {
            $min = (int) ($t['min'] ?? 0);
            $price = (int) ($t['price'] ?? 0);
            if ($min > 1 && $price > 0) {
                $tiers[] = ['min' => $min, 'price' => $price];
            }
        }
        usort($tiers, static fn ($a, $b) => $a['min'] <=> $b['min']);
        return $tiers;
    }

    /** @return array<int,array{name:string,choices:array<int,array{label:string,diff:int}>}> */
    public static function options(array $product): array
    {
        $raw = json_decode((string) ($product['options'] ?? ''), true);
        if (!is_array($raw)) {
            return [];
        }
        $groups = [];
        foreach ($raw as $g) {
            $name = trim((string) ($g['name'] ?? ''));
            $choices = [];
            foreach ((array) ($g['choices'] ?? []) as $c) {
                $label = trim((string) ($c['label'] ?? ''));
                if ($label !== '') {
                    $choices[] = ['label' => $label, 'diff' => (int) ($c['diff'] ?? 0)];
                }
            }
            if ($name !== '' && $choices !== []) {
                $groups[] = ['name' => $name, 'choices' => $choices];
            }
        }
        return $groups;
    }

    /** Grundpreis je Stück abhängig von der Menge (Staffelpreis). */
    public static function unitBasePrice(array $product, int $qty): int
    {
        $price = (int) $product['price'];
        foreach (self::tiers($product) as $tier) {
            if ($qty >= $tier['min']) {
                $price = $tier['price'];
            }
        }
        return $price;
    }

    /**
     * Aufpreis der gewählten Optionen und lesbare Beschriftung.
     * @param array<string,string> $selected  [Gruppenname => gewählte Beschriftung]
     * @return array{diff:int,label:string,clean:array<string,string>}
     */
    public static function resolveOptions(array $product, array $selected): array
    {
        $diff = 0;
        $parts = [];
        $clean = [];
        foreach (self::options($product) as $group) {
            $chosen = (string) ($selected[$group['name']] ?? '');
            $match = null;
            foreach ($group['choices'] as $choice) {
                if ($choice['label'] === $chosen) {
                    $match = $choice;
                    break;
                }
            }
            if ($match === null) {
                $match = $group['choices'][0]; // Fallback: erste Auswahl
            }
            $diff += $match['diff'];
            $clean[$group['name']] = $match['label'];
            $parts[] = $group['name'] . ': ' . $match['label'];
        }
        return ['diff' => $diff, 'label' => implode(', ', $parts), 'clean' => $clean];
    }

    /** Effektiver Stückpreis inkl. Staffel + Optionsaufpreis. */
    public static function unitPrice(array $product, int $qty, array $selected = []): int
    {
        return self::unitBasePrice($product, $qty) + self::resolveOptions($product, $selected)['diff'];
    }

    /** @return array<int,array> verknüpfte Produkte (cross_sell oder accessories) */
    public static function relatedProducts(array $product, string $field): array
    {
        $ids = json_decode((string) ($product[$field] ?? ''), true);
        if (!is_array($ids)) {
            return [];
        }
        $out = [];
        foreach ($ids as $id) {
            $rel = self::find((int) $id);
            if ($rel !== null && (int) $rel['active'] === 1) {
                $out[] = $rel;
            }
        }
        return $out;
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
