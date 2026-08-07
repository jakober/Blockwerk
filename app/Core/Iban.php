<?php
declare(strict_types=1);

namespace Core;

/** IBAN-Prüfsumme (ISO 7064 MOD 97-10) und Anzeige-Helfer – ohne externe Bibliothek. */
class Iban
{
    /** Leerzeichen entfernen, Großschreibung – Kanonische Form zum Speichern/Prüfen. */
    public static function normalize(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    }

    public static function isValid(string $iban): bool
    {
        $iban = self::normalize($iban);
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }
        // Prüfziffer: erste 4 Zeichen ans Ende, Buchstaben in Zahlen (A=10 … Z=35), MOD 97 muss 1 ergeben.
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }
        // bcmod wäre exakter, ist aber evtl. nicht immer verfügbar – schrittweise Modulo-Berechnung
        // in Blöcken funktioniert ohne bcmath und ohne Ganzzahl-Überlauf bei sehr langen IBANs.
        $remainder = 0;
        foreach (str_split($numeric) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }
        return $remainder === 1;
    }

    /** Für die Anzeige im Backend: nur die letzten 4 Stellen zeigen. */
    public static function mask(string $iban): string
    {
        $iban = self::normalize($iban);
        if (strlen($iban) <= 8) {
            return $iban;
        }
        return substr($iban, 0, 4) . ' •••• •••• ' . substr($iban, -4);
    }

    /** In lesbare 4er-Gruppen aufteilen (nur für Anzeige, nicht zum Speichern). */
    public static function format(string $iban): string
    {
        return trim(chunk_split(self::normalize($iban), 4, ' '));
    }
}
