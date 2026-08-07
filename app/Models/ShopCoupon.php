<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

/**
 * Rabattcodes: prozentual oder fester Betrag (Cent), optional mit Mindest-
 * bestellwert, Gültigkeitszeitraum und Nutzungslimit.
 */
class ShopCoupon
{
    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM shop_coupons ORDER BY created_at DESC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_coupons WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByCode(string $code): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_coupons WHERE code = ?');
        $stmt->execute([mb_strtoupper(trim($code))]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $d): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO shop_coupons (code, type, value, min_subtotal, starts_at, ends_at, usage_limit, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                mb_strtoupper(trim($d['code'])), $d['type'] === 'fixed' ? 'fixed' : 'percent', (int) $d['value'],
                $d['min_subtotal'] !== '' && $d['min_subtotal'] !== null ? (int) $d['min_subtotal'] : null,
                $d['starts_at'] ?: null, $d['ends_at'] ?: null,
                $d['usage_limit'] !== '' && $d['usage_limit'] !== null ? (int) $d['usage_limit'] : null,
                (int) ($d['active'] ?? 1),
            ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        Database::pdo()->prepare('UPDATE shop_coupons SET code = ?, type = ?, value = ?, min_subtotal = ?, starts_at = ?, ends_at = ?, usage_limit = ?, active = ? WHERE id = ?')
            ->execute([
                mb_strtoupper(trim($d['code'])), $d['type'] === 'fixed' ? 'fixed' : 'percent', (int) $d['value'],
                $d['min_subtotal'] !== '' && $d['min_subtotal'] !== null ? (int) $d['min_subtotal'] : null,
                $d['starts_at'] ?: null, $d['ends_at'] ?: null,
                $d['usage_limit'] !== '' && $d['usage_limit'] !== null ? (int) $d['usage_limit'] : null,
                (int) ($d['active'] ?? 1), $id,
            ]);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM shop_coupons WHERE id = ?')->execute([$id]);
    }

    /** Grund, warum der Gutschein (bei diesem Bestellwert) nicht gilt – null = gültig. */
    public static function invalidReason(array $coupon, int $subtotalCents): ?string
    {
        if ((int) $coupon['active'] !== 1) {
            return 'Dieser Gutschein ist nicht mehr aktiv.';
        }
        $now = date('Y-m-d H:i:s');
        if (!empty($coupon['starts_at']) && $coupon['starts_at'] > $now) {
            return 'Dieser Gutschein ist noch nicht gültig.';
        }
        if (!empty($coupon['ends_at']) && $coupon['ends_at'] < $now) {
            return 'Dieser Gutschein ist abgelaufen.';
        }
        if ($coupon['usage_limit'] !== null && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
            return 'Dieser Gutschein wurde bereits zu oft eingelöst.';
        }
        if ($coupon['min_subtotal'] !== null && $subtotalCents < (int) $coupon['min_subtotal']) {
            return 'Mindestbestellwert für diesen Gutschein: ' . \Core\Shop::formatPrice((int) $coupon['min_subtotal']) . '.';
        }
        return null;
    }

    /** Rabatt in Cent für einen gegebenen Bestellwert (nie negativ, nie über den Bestellwert hinaus). */
    public static function discountFor(array $coupon, int $subtotalCents): int
    {
        $amount = $coupon['type'] === 'fixed'
            ? (int) $coupon['value']
            : (int) round($subtotalCents * ((int) $coupon['value']) / 100);
        return max(0, min($amount, $subtotalCents));
    }

    /**
     * Nutzung hochzählen – mit Schutz gegen Wettlaufsituationen (z. B. zwei
     * gleichzeitige Bestellungen mit demselben, knapp limitierten Code).
     */
    public static function tryIncrementUsage(int $id): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE shop_coupons SET used_count = used_count + 1
             WHERE id = ? AND (usage_limit IS NULL OR used_count < usage_limit)'
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
