<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

/** Merkliste eines Kundenkontos – nur für eingeloggte Kunden (keine Gastfunktion). */
class ShopWishlist
{
    /** @return array<int,array> Produkte auf der Merkliste, neueste zuerst. */
    public static function products(int $customerId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.* FROM shop_wishlist_items w
             JOIN shop_products p ON p.id = w.product_id
             WHERE w.customer_id = ? ORDER BY w.created_at DESC'
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    /** @return int[] Produkt-IDs auf der Merkliste (für schnelle Herz-Prüfung in Listen). */
    public static function productIds(int $customerId): array
    {
        $stmt = Database::pdo()->prepare('SELECT product_id FROM shop_wishlist_items WHERE customer_id = ?');
        $stmt->execute([$customerId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public static function has(int $customerId, int $productId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM shop_wishlist_items WHERE customer_id = ? AND product_id = ?');
        $stmt->execute([$customerId, $productId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function add(int $customerId, int $productId): void
    {
        Database::pdo()->prepare('INSERT IGNORE INTO shop_wishlist_items (customer_id, product_id) VALUES (?, ?)')
            ->execute([$customerId, $productId]);
    }

    public static function remove(int $customerId, int $productId): void
    {
        Database::pdo()->prepare('DELETE FROM shop_wishlist_items WHERE customer_id = ? AND product_id = ?')
            ->execute([$customerId, $productId]);
    }
}
