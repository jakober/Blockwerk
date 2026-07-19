<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class ShopShipping
{
    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM shop_shipping ORDER BY position, name')->fetchAll();
    }

    public static function active(): array
    {
        return Database::pdo()->query('SELECT * FROM shop_shipping WHERE active = 1 ORDER BY position, name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shop_shipping WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $d): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO shop_shipping (name, description, price, free_from, countries, weight_tiers, active, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $d['name'], $d['description'] ?? null, (int) $d['price'],
                $d['free_from'] !== '' && $d['free_from'] !== null ? (int) $d['free_from'] : null,
                $d['countries'] ?? null, $d['weight_tiers'] ?? null,
                (int) ($d['active'] ?? 1), (int) ($d['position'] ?? 0),
            ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        Database::pdo()->prepare('UPDATE shop_shipping SET name = ?, description = ?, price = ?, free_from = ?, countries = ?, weight_tiers = ?, active = ?, position = ? WHERE id = ?')
            ->execute([
                $d['name'], $d['description'] ?? null, (int) $d['price'],
                $d['free_from'] !== '' && $d['free_from'] !== null ? (int) $d['free_from'] : null,
                $d['countries'] ?? null, $d['weight_tiers'] ?? null,
                (int) ($d['active'] ?? 1), (int) ($d['position'] ?? 0), $id,
            ]);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM shop_shipping WHERE id = ?')->execute([$id]);
    }

    /** Länder, für die diese Versandart gilt (leer = alle Länder). */
    public static function countries(array $method): array
    {
        $raw = json_decode((string) ($method['countries'] ?? ''), true);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $c) {
            $c = trim((string) $c);
            if ($c !== '') {
                $out[] = $c;
            }
        }
        return $out;
    }

    /** Gewichtsstaffeln [{max:Gramm, price:Cent}] aufsteigend nach Obergrenze. */
    public static function weightTiers(array $method): array
    {
        $raw = json_decode((string) ($method['weight_tiers'] ?? ''), true);
        if (!is_array($raw)) {
            return [];
        }
        $tiers = [];
        foreach ($raw as $t) {
            $max = (int) ($t['max'] ?? 0);
            $price = (int) ($t['price'] ?? 0);
            if ($max > 0) {
                $tiers[] = ['max' => $max, 'price' => max(0, $price)];
            }
        }
        usort($tiers, static fn ($a, $b) => $a['max'] <=> $b['max']);
        return $tiers;
    }

    /** Gilt die Versandart für dieses Land? (Leere Länderliste = alle Länder.) */
    public static function servesCountry(array $method, string $country): bool
    {
        $countries = self::countries($method);
        if ($countries === []) {
            return true;
        }
        $country = mb_strtolower(trim($country));
        foreach ($countries as $c) {
            if (mb_strtolower($c) === $country) {
                return true;
            }
        }
        return false;
    }

    /** Aktive Versandarten, die in das gewählte Land liefern. */
    public static function availableFor(string $country): array
    {
        return array_values(array_filter(self::active(), static fn ($m) => self::servesCountry($m, $country)));
    }

    /** Gesamtes Liefergebiet (Vereinigung aller Länder aktiver Versandarten). */
    public static function allCountries(): array
    {
        $set = [];
        foreach (self::active() as $m) {
            foreach (self::countries($m) as $c) {
                $set[mb_strtolower($c)] = $c;
            }
        }
        $out = array_values($set);
        sort($out);
        return $out;
    }

    /**
     * Versandkosten für Zwischensumme + Warenkorbgewicht (in Gramm).
     * Reihenfolge: „gratis ab" schlägt alles; sonst Gewichtsstaffel (falls
     * gepflegt), sonst der pauschale Preis. Kein/0-Gewicht = niedrigste Staffel.
     */
    /** Preis nach Gewicht (bzw. pauschal) – OHNE „gratis ab". Kein Gewicht = niedrigste Staffel. */
    public static function basePrice(array $method, int $weightGrams = 0): int
    {
        $tiers = self::weightTiers($method);
        if ($tiers !== []) {
            foreach ($tiers as $tier) {
                if ($weightGrams <= $tier['max']) {
                    return $tier['price'];
                }
            }
            // Gewicht über allen Staffeln: teuerste (letzte) Staffel.
            return $tiers[count($tiers) - 1]['price'];
        }
        return (int) $method['price'];
    }

    public static function costFor(array $method, int $subtotal, int $weightGrams = 0): int
    {
        $freeFrom = $method['free_from'] ?? null;
        if ($freeFrom !== null && $freeFrom !== '' && $subtotal >= (int) $freeFrom) {
            return 0;
        }
        return self::basePrice($method, $weightGrams);
    }
}
