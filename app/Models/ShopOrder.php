<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class ShopOrder
{
    /** Fulfillment-Status → Label (Frontend & Admin gemeinsam). */
    public const STATUS_LABELS = [
        'new' => 'Neu',
        'paid' => 'Bezahlt',
        'shipped' => 'Versendet',
        'cancelled' => 'Storniert',
        'refunded' => 'Erstattet',
    ];

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    /** CSS-Badge-Klasse zum Status (wie im Admin). */
    public static function statusBadge(string $status): string
    {
        return match ($status) {
            'paid', 'shipped' => 'badge-green',
            'new' => 'badge-amber',
            default => 'badge',
        };
    }

    /** Bestellungen eines Kunden – per customer_id ODER (frühere Gastbestellungen) per E-Mail. */
    /** Bestellung zu einer PayPal-Order-/Transaktions-ID (Dedupe bei Webhooks). */
    public static function findByPaypalOrderId(string $paypalOrderId): ?array
    {
        if ($paypalOrderId === '') {
            return null;
        }
        try {
            $stmt = Database::pdo()->prepare('SELECT * FROM shop_orders WHERE paypal_order_id = ? LIMIT 1');
            $stmt->execute([$paypalOrderId]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function forCustomer(int $customerId, string $email): array
    {
        $mail = mb_strtolower(trim($email));
        try {
            $stmt = Database::pdo()->prepare(
                'SELECT * FROM shop_orders WHERE customer_id = ? OR LOWER(email) = ? ORDER BY created_at DESC'
            );
            $stmt->execute([$customerId, $mail]);
            return $stmt->fetchAll();
        } catch (\Throwable) {
            // Falls die Spalte customer_id (noch) fehlt: nur nach E-Mail.
            $stmt = Database::pdo()->prepare('SELECT * FROM shop_orders WHERE LOWER(email) = ? ORDER BY created_at DESC');
            $stmt->execute([$mail]);
            return $stmt->fetchAll();
        }
    }

    /**
     * Stellt die Spalte shop_orders.customer_id sicher (Selbstheilung, falls die
     * Update-Migration nicht durchlief). Cacht das Ergebnis pro Request.
     */
    private static function ensureCustomerColumn(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        $pdo = Database::pdo();
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'customer_id'");
            $st->execute();
            if ((int) $st->fetchColumn() === 0) {
                $pdo->exec('ALTER TABLE shop_orders ADD COLUMN customer_id INT UNSIGNED NULL');
            }
            $ok = true;
        } catch (\Throwable) {
            $ok = false;
        }
        return $ok;
    }

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

    /** Hat der Kunde dieses Produkt schon einmal bestellt? (Grundlage für Bewertungen.) */
    public static function customerPurchasedProduct(int $customerId, int $productId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM shop_orders o JOIN shop_order_items i ON i.order_id = o.id
             WHERE o.customer_id = ? AND i.product_id = ? LIMIT 1'
        );
        $stmt->execute([$customerId, $productId]);
        return (bool) $stmt->fetchColumn();
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
        // WICHTIG vor beginTransaction: ein evtl. nötiges ALTER TABLE (Selbstheilung
        // der customer_id-Spalte) löst in MySQL/MariaDB einen impliziten COMMIT aus –
        // innerhalb einer Transaktion würde das commit() später fehlschlagen.
        $hasCustomer = self::ensureCustomerColumn();
        $pdo->beginTransaction();
        $number = self::nextNumber($pdo);
        $cols = ['number', 'token', 'status', 'email', 'first_name', 'last_name', 'company', 'street', 'zip', 'city',
            'country', 'phone', 'note', 'subtotal', 'shipping_cost', 'total', 'currency', 'shipping_method',
            'payment_method', 'payment_status', 'paypal_order_id', 'coupon_code', 'discount_cents'];
        $vals = [
            $number, $order['token'], $order['status'] ?? 'new', $order['email'],
            $order['first_name'] ?? null, $order['last_name'] ?? null, $order['company'] ?? null,
            $order['street'] ?? null, $order['zip'] ?? null, $order['city'] ?? null, $order['country'] ?? null,
            $order['phone'] ?? null, $order['note'] ?? null,
            (int) $order['subtotal'], (int) $order['shipping_cost'], (int) $order['total'],
            $order['currency'] ?? 'EUR', $order['shipping_method'] ?? null, $order['payment_method'] ?? null,
            $order['payment_status'] ?? 'pending', $order['paypal_order_id'] ?? null,
            $order['coupon_code'] ?? null, (int) ($order['discount_cents'] ?? 0),
        ];
        // SEPA-Mandat: Referenz an die (erst hier bekannte) Bestellnummer knüpfen,
        // damit sie eindeutig und für den Kunden nachvollziehbar ist.
        if (($order['payment_method'] ?? '') === 'sepa') {
            $cols[] = 'sepa_iban';
            $vals[] = $order['sepa_iban'] ?? null;
            $cols[] = 'sepa_bic';
            $vals[] = $order['sepa_bic'] ?? null;
            $cols[] = 'sepa_account_holder';
            $vals[] = $order['sepa_account_holder'] ?? null;
            $cols[] = 'sepa_mandate_ref';
            $vals[] = 'M' . preg_replace('/[^A-Za-z0-9]/', '', $number);
            $cols[] = 'sepa_mandate_date';
            $vals[] = date('Y-m-d');
        }
        // customer_id nur mitschreiben, wenn die Spalte existiert (Selbstheilung).
        if ($hasCustomer) {
            $cols[] = 'customer_id';
            $vals[] = !empty($order['customer_id']) ? (int) $order['customer_id'] : null;
        }
        $pdo->prepare('INSERT INTO shop_orders (' . implode(', ', $cols) . ') VALUES ('
            . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute($vals);
        $orderId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO shop_order_items (order_id, product_id, name, sku, price, qty, tax_rate) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($items as $it) {
            $stmt->execute([$orderId, $it['product_id'] ?: null, $it['name'], $it['sku'] ?? null, (int) $it['price'], (int) $it['qty'], $it['tax_rate'] ?? null]);
        }
        // Gutschein-Nutzung erst nach erfolgreichem Anlegen der Bestellung zählen.
        if (!empty($order['coupon_code'])) {
            $coupon = \Models\ShopCoupon::findByCode((string) $order['coupon_code']);
            if ($coupon !== null) {
                \Models\ShopCoupon::tryIncrementUsage((int) $coupon['id']);
            }
        }
        $pdo->commit();
        return $orderId;
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::pdo()->prepare('UPDATE shop_orders SET status = ? WHERE id = ?')->execute([$status, $id]);
    }

    public static function setTracking(int $id, ?string $number, ?string $url): void
    {
        Database::pdo()->prepare('UPDATE shop_orders SET tracking_number = ?, tracking_url = ? WHERE id = ?')
            ->execute([$number, $url, $id]);
    }

    /** Noch nicht eingereichte SEPA-Lastschriften (payment_status noch offen). */
    public static function pendingSepa(): array
    {
        return Database::pdo()
            ->query("SELECT * FROM shop_orders WHERE payment_method = 'sepa' AND payment_status = 'pending' ORDER BY created_at")
            ->fetchAll();
    }

    /** Markiert Bestellungen als in einer SEPA-Sammeldatei eingereicht. */
    public static function markSepaSubmitted(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        Database::pdo()->prepare("UPDATE shop_orders SET sepa_submitted_at = NOW() WHERE id IN ($placeholders)")->execute($ids);
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
