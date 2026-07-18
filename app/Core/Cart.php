<?php
declare(strict_types=1);

namespace Core;

use Models\ShopProduct;

/**
 * Sitzungsbasierter Warenkorb: speichert nur Produkt-IDs und Mengen in der
 * Session; Namen/Preise werden beim Auslesen frisch aus der Datenbank geholt
 * (so bleiben Preisänderungen korrekt).
 */
class Cart
{
    private const KEY = 'shop_cart';

    private static function &store(): array
    {
        if (!isset($_SESSION[self::KEY]) || !is_array($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = [];
        }
        return $_SESSION[self::KEY];
    }

    public static function add(int $productId, int $qty = 1): void
    {
        if ($productId <= 0 || $qty === 0) {
            return;
        }
        $cart = &self::store();
        $cart[$productId] = max(1, ($cart[$productId] ?? 0) + $qty);
    }

    public static function set(int $productId, int $qty): void
    {
        $cart = &self::store();
        if ($qty <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $qty;
        }
    }

    public static function remove(int $productId): void
    {
        $cart = &self::store();
        unset($cart[$productId]);
    }

    public static function clear(): void
    {
        $_SESSION[self::KEY] = [];
    }

    /** Anzahl Artikel (Summe der Mengen). */
    public static function count(): int
    {
        return array_sum(self::store());
    }

    public static function isEmpty(): bool
    {
        return self::store() === [];
    }

    /**
     * Warenkorb-Positionen mit frischen Produktdaten.
     * @return array<int, array{product:array, qty:int, line:int}>
     */
    public static function items(): array
    {
        $out = [];
        $cart = self::store();
        $changed = false;
        foreach ($cart as $productId => $qty) {
            $product = ShopProduct::find((int) $productId);
            if ($product === null || (int) $product['active'] !== 1) {
                unset($_SESSION[self::KEY][$productId]); // nicht mehr verfügbar
                $changed = true;
                continue;
            }
            $qty = max(1, (int) $qty);
            $out[] = [
                'product' => $product,
                'qty' => $qty,
                'line' => (int) $product['price'] * $qty,
            ];
        }
        unset($changed);
        return $out;
    }

    public static function subtotal(): int
    {
        $sum = 0;
        foreach (self::items() as $item) {
            $sum += $item['line'];
        }
        return $sum;
    }
}
