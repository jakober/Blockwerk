<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

/**
 * Warenkorb-Zweitspeicher für eingeloggte Kunden (Core\Cart bleibt die
 * eigentliche Quelle während der Sitzung – hier wird sie nur gespiegelt,
 * damit ein Kunde seinen Warenkorb nach Login auf einem anderen Gerät
 * wiederfindet).
 */
class ShopCartItem
{
    /** @return array<string,array{id:int,qty:int,opts:array}> gleiche Form wie Core\Cart::store(). */
    public static function forCustomer(int $customerId): array
    {
        $stmt = Database::pdo()->prepare('SELECT cart_key, product_id, opts_json, qty FROM shop_cart_items WHERE customer_id = ?');
        $stmt->execute([$customerId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $opts = json_decode((string) ($row['opts_json'] ?? ''), true);
            $out[$row['cart_key']] = [
                'id' => (int) $row['product_id'],
                'qty' => (int) $row['qty'],
                'opts' => is_array($opts) ? $opts : [],
            ];
        }
        return $out;
    }

    /** Ersetzt den gesamten gespeicherten Warenkorb eines Kunden durch den aktuellen Stand. */
    public static function replaceAll(int $customerId, array $entries): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM shop_cart_items WHERE customer_id = ?')->execute([$customerId]);
        if ($entries !== []) {
            $stmt = $pdo->prepare('INSERT INTO shop_cart_items (customer_id, cart_key, product_id, opts_json, qty) VALUES (?, ?, ?, ?, ?)');
            foreach ($entries as $key => $entry) {
                $stmt->execute([
                    $customerId, (string) $key, (int) ($entry['id'] ?? 0),
                    json_encode($entry['opts'] ?? [], JSON_UNESCAPED_UNICODE), max(1, (int) ($entry['qty'] ?? 1)),
                ]);
            }
        }
        $pdo->commit();
    }
}
