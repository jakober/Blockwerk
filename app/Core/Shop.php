<?php
declare(strict_types=1);

namespace Core;

use Models\Page;
use Models\Setting;

/**
 * Zentrale Shop-Einstellungen und Hilfsfunktionen (Preise, URLs, Zahlungs-
 * und PayPal-Konfiguration). Preise werden überall in Cent (Ganzzahl)
 * geführt, um Rundungsfehler zu vermeiden.
 */
class Shop
{
    public static function enabled(): bool
    {
        return Setting::get('shop_enabled', '0') === '1';
    }

    public static function rootPageId(): int
    {
        return (int) Setting::get('shop_root_page', '0');
    }

    /** Slug der Shop-Hauptseite (Basis aller Shop-URLs), Rückfall "shop". */
    public static function rootSlug(): string
    {
        $id = self::rootPageId();
        if ($id > 0) {
            $page = Page::find($id);
            if ($page !== null && ($page['slug'] ?? '') !== '') {
                return (string) $page['slug'];
            }
        }
        return 'shop';
    }

    public static function currency(): string
    {
        return Setting::get('shop_currency', 'EUR') ?: 'EUR';
    }

    public static function currencySymbol(): string
    {
        return Setting::get('shop_currency_symbol', '€') ?: '€';
    }

    /** Cent → "12,90 €". */
    public static function formatPrice(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.') . ' ' . self::currencySymbol();
    }

    /** Eingabe "12,90" oder "12.90" → Cent. */
    public static function parsePrice(string $input): int
    {
        $input = trim(str_replace([' ', "\xc2\xa0"], '', $input));
        $input = str_replace(',', '.', $input);
        if ($input === '' || !is_numeric($input)) {
            return 0;
        }
        return (int) round(((float) $input) * 100);
    }

    public static function paymentMethods(): array
    {
        $methods = [];
        if (Setting::get('shop_pay_invoice', '1') === '1') {
            $methods['invoice'] = 'Kauf auf Rechnung';
        }
        if (Setting::get('shop_pay_prepay', '1') === '1') {
            $methods['prepay'] = 'Vorkasse (Überweisung)';
        }
        if (Setting::get('shop_pay_paypal', '0') === '1' && self::paypalClientId() !== '') {
            $methods['paypal'] = 'PayPal';
        }
        return $methods;
    }

    public static function paypalClientId(): string
    {
        return Setting::get('shop_paypal_client_id', '');
    }

    public static function paypalSecret(): string
    {
        return Setting::get('shop_paypal_secret', '');
    }

    public static function paypalSandbox(): bool
    {
        return Setting::get('shop_paypal_sandbox', '1') === '1';
    }

    public static function paypalApiBase(): string
    {
        return self::paypalSandbox()
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /** Absolute Shop-URL, z. B. Shop::url('produkt/foo'). */
    public static function url(string $path = ''): string
    {
        $base = '/' . trim(self::rootSlug(), '/');
        $path = trim($path, '/');
        return url($base . ($path !== '' ? '/' . $path : ''));
    }
}
