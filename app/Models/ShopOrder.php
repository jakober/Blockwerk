<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class ShopOrder
{
    public static function all(?string $status = null): array
    {
        if ($status !== null && $status !== '') {
            $stmt = Database::pdo()->prepare('SELECT * FROM shop_orders WHERE status = ? ORDER BY created_at DESC');
            $stmt->execute([$status]);
            return $stmt->fetchAll();
        }
        return Database::pdo()->query('SELECT * FROM shop_orders ORDER BY created_at DESC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_orders WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByToken(string $token): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_orders WHERE token = ?');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public static function items(int $orderId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_order_items WHERE order_id = ? ORDER BY id');
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public static function countNew(): int
    {
        return (int) Database::pdo()->query("SELECT COUNT(*) FROM shop_orders WHERE status = 'new'")->fetchColumn();
    }

    /**
     * Bestellung mit Positionen in einer Transaktion anlegen.
     * @param array $order  Kopf-Daten
     * @param array $items  [['product_id','name','sku','price','qty'], …]
     */
    public static function create(array $order, array $items): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $number = self::nextNumber($pdo);
        $pdo->prepare('INSERT INTO shop_orders
            (number, token, status, email, first_name, last_name, company, street, zip, city, country, phone, note,
             subtotal, shipping_cost, total, currency, shipping_method, payment_method, payment_status, paypal_order_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $number, $order['token'], $order['status'] ?? 'new', $order['email'],
                $order['first_name'] ?? null, $order['last_name'] ?? null, $order['company'] ?? null,
                $order['street'] ?? null, $order['zip'] ?? null, $order['city'] ?? null, $order['country'] ?? null,
                $order['phone'] ?? null, $order['note'] ?? null,
                (int) $order['subtotal'], (int) $order['shipping_cost'], (int) $order['total'],
                $order['currency'] ?? 'EUR', $order['shipping_method'] ?? null, $order['payment_method'] ?? null,
                $order['payment_status'] ?? 'pending', $order['paypal_order_id'] ?? null,
            ]);
        $orderId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO shop_order_items (order_id, product_id, name, sku, price, qty) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($items as $it) {
            $stmt->execute([$orderId, $it['product_id'] ?: null, $it['name'], $it['sku'] ?? null, (int) $it['price'], (int) $it['qty']]);
        }
        $pdo->commit();
        return $orderId;
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::pdo()->prepare('UPDATE shop_orders SET status = ? WHERE id = ?')->execute([$status, $id]);
    }

    public static function setPaid(int $id, ?string $paypalId = null): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE shop_orders SET payment_status = ?, status = ?, paypal_order_id = COALESCE(?, paypal_order_id) WHERE id = ?')
            ->execute(['paid', 'paid', $paypalId, $id]);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM shop_order_items WHERE order_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM shop_orders WHERE id = ?')->execute([$id]);
    }

    private static function nextNumber(\PDO $pdo): string
    {
        $year = date('Y');
        $count = (int) $pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn();
        return $year . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
