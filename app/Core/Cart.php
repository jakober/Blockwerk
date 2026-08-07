<?php
declare(strict_types=1);

namespace Core;

use Models\ShopCartItem;
use Models\ShopCoupon;
use Models\ShopProduct;

/**
 * Sitzungsbasierter Warenkorb. Ein Eintrag ist durch Produkt-ID UND gewählte
 * Optionen (z. B. Größe) eindeutig – dasselbe Produkt in zwei Größen sind zwei
 * Positionen. Gespeichert wird nur das Nötigste; Preise werden beim Auslesen
 * frisch berechnet (inkl. Staffelpreis und Optionsaufpreis).
 */
class Cart
{
    private const KEY = 'shop_cart';

    /** @return array<string,array{id:int,qty:int,opts:array}> */
    private static function &store(): array
    {
        if (!isset($_SESSION[self::KEY]) || !is_array($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = [];
        }
        return $_SESSION[self::KEY];
    }

    /** Eindeutiger Schlüssel aus Produkt-ID + (sortierten) Optionen. */
    private static function keyFor(int $productId, array $opts): string
    {
        ksort($opts);
        $parts = [];
        foreach ($opts as $group => $choice) {
            $parts[] = $group . '=' . $choice;
        }
        return $productId . ($parts !== [] ? '|' . implode('|', $parts) : '');
    }

    public static function add(int $productId, int $qty = 1, array $opts = []): void
    {
        $product = ShopProduct::find($productId);
        if ($product === null || (int) $product['active'] !== 1 || $qty === 0) {
            return;
        }
        $clean = ShopProduct::resolveOptions($product, $opts)['clean'];
        $key = self::keyFor($productId, $clean);
        $cart = &self::store();
        if (isset($cart[$key])) {
            $cart[$key]['qty'] = max(1, (int) $cart[$key]['qty'] + $qty);
        } else {
            $cart[$key] = ['id' => $productId, 'qty' => max(1, $qty), 'opts' => $clean];
        }
        self::syncToDb();
    }

    public static function set(string $key, int $qty): void
    {
        $cart = &self::store();
        if (!isset($cart[$key])) {
            return;
        }
        if ($qty <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key]['qty'] = $qty;
        }
        self::syncToDb();
    }

    public static function remove(string $key): void
    {
        $cart = &self::store();
        unset($cart[$key]);
        self::syncToDb();
    }

    public static function clear(): void
    {
        $_SESSION[self::KEY] = [];
        self::removeCoupon();
        self::syncToDb();
    }

    /** Aktuellen Warenkorb für eingeloggte Kunden in die Datenbank spiegeln. */
    private static function syncToDb(): void
    {
        if (CustomerAuth::check()) {
            ShopCartItem::replaceAll((int) CustomerAuth::id(), self::store());
        }
    }

    /**
     * Beim Login einen evtl. gespeicherten Warenkorb in die aktuelle Sitzung
     * mergen (Mengen werden addiert) – so findet ein Kunde seinen Warenkorb
     * auf einem neuen Gerät wieder.
     */
    public static function mergeFromDb(int $customerId): void
    {
        foreach (ShopCartItem::forCustomer($customerId) as $entry) {
            self::add((int) $entry['id'], (int) $entry['qty'], (array) $entry['opts']);
        }
    }

    public static function count(): int
    {
        $sum = 0;
        foreach (self::store() as $entry) {
            $sum += (int) ($entry['qty'] ?? 0);
        }
        return $sum;
    }

    public static function isEmpty(): bool
    {
        return self::items() === [];
    }

    /**
     * Warenkorb-Positionen mit frischen Produktdaten und berechneten Preisen.
     * @return array<int,array{key:string,product:array,qty:int,opts:array,optionLabel:string,unit:int,line:int}>
     */
    public static function items(): array
    {
        $out = [];
        foreach (self::store() as $key => $entry) {
            $product = ShopProduct::find((int) ($entry['id'] ?? 0));
            if ($product === null || (int) $product['active'] !== 1) {
                unset($_SESSION[self::KEY][$key]); // nicht mehr verfügbar
                continue;
            }
            $qty = max(1, (int) ($entry['qty'] ?? 1));
            $opts = is_array($entry['opts'] ?? null) ? $entry['opts'] : [];
            $resolved = ShopProduct::resolveOptions($product, $opts);
            $unit = ShopProduct::unitBasePrice($product, $qty) + $resolved['diff'];
            $out[] = [
                'key' => (string) $key,
                'product' => $product,
                'qty' => $qty,
                'opts' => $resolved['clean'],
                'optionLabel' => $resolved['label'],
                'unit' => $unit,
                'line' => $unit * $qty,
            ];
        }
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

    /** Gesamtgewicht des Warenkorbs in Gramm (fehlendes Produktgewicht = 0). */
    public static function weight(): int
    {
        $sum = 0;
        foreach (self::items() as $item) {
            $sum += max(0, (int) ($item['product']['weight'] ?? 0)) * (int) $item['qty'];
        }
        return $sum;
    }

    /* ---------- Gutschein ---------- */

    private const COUPON_KEY = 'shop_coupon_code';

    /**
     * Gutscheincode prüfen und in der Sitzung merken.
     * @return array{ok:bool,error:?string}
     */
    public static function applyCoupon(string $code): array
    {
        $coupon = ShopCoupon::findByCode($code);
        if ($coupon === null) {
            return ['ok' => false, 'error' => 'Diesen Gutscheincode gibt es nicht.'];
        }
        $reason = ShopCoupon::invalidReason($coupon, self::subtotal());
        if ($reason !== null) {
            return ['ok' => false, 'error' => $reason];
        }
        $_SESSION[self::COUPON_KEY] = $coupon['code'];
        return ['ok' => true, 'error' => null];
    }

    public static function removeCoupon(): void
    {
        unset($_SESSION[self::COUPON_KEY]);
    }

    /** Aktuell hinterlegter, noch gültiger Gutschein – wird bei Ungültigkeit automatisch entfernt. */
    public static function coupon(): ?array
    {
        $code = $_SESSION[self::COUPON_KEY] ?? null;
        if (!is_string($code) || $code === '') {
            return null;
        }
        $coupon = ShopCoupon::findByCode($code);
        if ($coupon === null || ShopCoupon::invalidReason($coupon, self::subtotal()) !== null) {
            self::removeCoupon();
            return null;
        }
        return $coupon;
    }

    public static function discount(): int
    {
        $coupon = self::coupon();
        return $coupon !== null ? ShopCoupon::discountFor($coupon, self::subtotal()) : 0;
    }

    public static function total(): int
    {
        return max(0, self::subtotal() - self::discount());
    }
}
