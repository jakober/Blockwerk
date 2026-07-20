<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

/**
 * Rechnungen zu Bestellungen. Eine Rechnungsnummer entsteht NICHT automatisch
 * bei der Bestellung, sondern erst wenn im Backend „Rechnung erstellen" gedrückt
 * wird – dann mit fortlaufender Nummer. Die Tabelle heilt sich selbst (für
 * Bestandsinstallationen, bei denen das Update nicht vollständig durchlief).
 */
class Invoice
{
    private static bool $ensured = false;

    public static function ensureTable(): void
    {
        if (self::$ensured) {
            return;
        }
        try {
            Database::pdo()->exec(
                'CREATE TABLE IF NOT EXISTS invoices (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT UNSIGNED NOT NULL,
                    seq INT NOT NULL,
                    number VARCHAR(40) NOT NULL,
                    created_at DATETIME NOT NULL,
                    UNIQUE KEY uniq_order (order_id),
                    UNIQUE KEY uniq_seq (seq)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        } catch (\Throwable) {
        }
        self::$ensured = true;
    }

    public static function findByOrder(int $orderId): ?array
    {
        self::ensureTable();
        try {
            $stmt = Database::pdo()->prepare('SELECT * FROM invoices WHERE order_id = ?');
            $stmt->execute([$orderId]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Erstellt (falls noch nicht vorhanden) die Rechnung zur Bestellung mit
     * fortlaufender Nummer und gibt den Datensatz zurück.
     */
    public static function createForOrder(int $orderId): array
    {
        self::ensureTable();
        $existing = self::findByOrder($orderId);
        if ($existing !== null) {
            return $existing;
        }
        $prefix = (string) Setting::get('shop_invoice_prefix', 'RE-');
        $start = max(1, (int) Setting::get('shop_invoice_start', '1'));

        $pdo = Database::pdo();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $max = (int) $pdo->query('SELECT COALESCE(MAX(seq), 0) FROM invoices')->fetchColumn();
            $seq = max($max + 1, $start);
            $number = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            try {
                $stmt = $pdo->prepare('INSERT INTO invoices (order_id, seq, number, created_at) VALUES (?, ?, ?, NOW())');
                $stmt->execute([$orderId, $seq, $number]);
                break;
            } catch (\Throwable) {
                // Nummern-Kollision (paralleler Zugriff) oder Bestellung hat
                // bereits eine Rechnung – erneut prüfen.
                $again = self::findByOrder($orderId);
                if ($again !== null) {
                    return $again;
                }
                usleep(20000);
            }
        }
        return self::findByOrder($orderId) ?? [
            'order_id' => $orderId,
            'seq' => 0,
            'number' => $prefix . '0000',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}
