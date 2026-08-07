<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

/**
 * Produktbewertungen – nur eingeloggte Kunden, die das Produkt nachweislich
 * bestellt haben (siehe ShopOrder::customerPurchasedProduct()), können eine
 * abgeben. Jede Bewertung braucht eine Freigabe im Backend, bevor sie
 * öffentlich sichtbar wird (Schutz vor Spam/Missbrauch).
 */
class ShopReview
{
    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM shop_reviews ORDER BY created_at DESC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_reviews WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function approvedForProduct(int $productId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_reviews WHERE product_id = ? AND approved = 1 ORDER BY created_at DESC');
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    /** @return array{count:int,avg:float} */
    public static function summaryForProduct(int $productId): array
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) AS c, AVG(rating) AS a FROM shop_reviews WHERE product_id = ? AND approved = 1');
        $stmt->execute([$productId]);
        $row = $stmt->fetch();
        return ['count' => (int) ($row['c'] ?? 0), 'avg' => round((float) ($row['a'] ?? 0), 1)];
    }

    public static function hasReviewed(int $customerId, int $productId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM shop_reviews WHERE customer_id = ? AND product_id = ?');
        $stmt->execute([$customerId, $productId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function countPending(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM shop_reviews WHERE approved = 0')->fetchColumn();
    }

    public static function create(int $productId, int $customerId, string $name, int $rating, string $text): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO shop_reviews (product_id, customer_id, name, rating, text, approved) VALUES (?, ?, ?, ?, ?, 0)')
            ->execute([$productId, $customerId, $name, max(1, min(5, $rating)), $text !== '' ? $text : null]);
        return (int) $pdo->lastInsertId();
    }

    public static function approve(int $id): void
    {
        Database::pdo()->prepare('UPDATE shop_reviews SET approved = 1 WHERE id = ?')->execute([$id]);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM shop_reviews WHERE id = ?')->execute([$id]);
    }
}
