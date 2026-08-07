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

    /** 'none' = Kleinunternehmer §19 UStG (keine MwSt.-Ausweisung), 'inclusive' = Bruttopreise inkl. MwSt. */
    public static function taxMode(): string
    {
        $mode = Setting::get('shop_tax_mode', 'none');
        return $mode === 'inclusive' ? 'inclusive' : 'none';
    }

    /** Standard-Steuersatz in Prozent (greift, wenn ein Produkt keinen eigenen hat). */
    public static function defaultTaxRate(): float
    {
        $rate = (float) str_replace(',', '.', (string) Setting::get('shop_default_tax_rate', '19'));
        return $rate >= 0 && $rate <= 100 ? $rate : 19.0;
    }

    /** Steuer-Modus + Standardsatz setzen ('inclusive' = Bruttopreise inkl. MwSt., sonst Kleinunternehmer §19 UStG). */
    public static function setTaxMode(string $mode, float $rate): void
    {
        $mode = $mode === 'inclusive' ? 'inclusive' : 'none';
        Setting::set('shop_tax_mode', $mode);
        $rate = max(0.0, min(100.0, $rate));
        Setting::set('shop_default_tax_rate', rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') ?: '0');
    }

    /** Steuersatz eines Produkts – eigener Satz, sonst der Standardsatz. */
    public static function productTaxRate(array $product): float
    {
        $own = $product['tax_rate'] ?? null;
        return $own !== null && $own !== '' ? (float) $own : self::defaultTaxRate();
    }

    /** In einem Bruttobetrag enthaltene Steuer (Cent), bei gegebenem Prozentsatz. */
    public static function taxAmount(int $grossCents, float $rate): int
    {
        if ($rate <= 0) {
            return 0;
        }
        return (int) round($grossCents - $grossCents / (1 + $rate / 100));
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
        if (Setting::get('shop_pay_sepa', '0') === '1' && self::sepaCreditorId() !== '' && self::sepaIban() !== '') {
            $methods['sepa'] = 'SEPA-Lastschrift';
        }
        return $methods;
    }

    /* ---------- SEPA-Lastschrift (ohne Zahlungsdienstleister) ---------- */

    public static function sepaCreditorId(): string
    {
        return trim((string) Setting::get('shop_sepa_creditor_id', ''));
    }

    /** Gläubiger-Name für das Mandat – eigener Wert, sonst Firma aus den Rechnungsdaten. */
    public static function sepaCreditorName(): string
    {
        $name = trim((string) Setting::get('shop_sepa_creditor_name', ''));
        return $name !== '' ? $name : self::invoiceName();
    }

    public static function sepaIban(): string
    {
        return Iban::normalize((string) Setting::get('shop_sepa_iban', ''));
    }

    public static function sepaBic(): string
    {
        return trim((string) Setting::get('shop_sepa_bic', ''));
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
        // Optionaler Override (z. B. für einen eigenen PayPal-kompatiblen Endpunkt
        // oder lokale Tests). Leer = offizielle Sandbox-/Live-URL.
        $override = rtrim(trim((string) Setting::get('shop_paypal_api_base', '')), '/');
        if ($override !== '') {
            return $override;
        }
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

    /** Name für Rechnung/Mails: Rechnungs-Firma, sonst Website-Name. */
    public static function invoiceName(): string
    {
        $c = trim((string) \Models\Setting::get('shop_invoice_company', ''));
        return $c !== '' ? $c : (string) \Models\Setting::get('site_name', 'Shop');
    }

    /**
     * Logo für die Rechnung: eigenes Rechnungs-Logo, sonst das Logo der Website
     * (aus dem l-brand-Block des Standard-Layouts). Leer, wenn keins gefunden.
     */
    public static function invoiceLogo(): string
    {
        $logo = trim((string) \Models\Setting::get('shop_invoice_logo', ''));
        if ($logo !== '') {
            return $logo;
        }
        // Logo der Website (Einstellungen) – gilt für jedes Layout.
        $siteLogo = trim((string) \Models\Setting::get('site_logo', ''));
        if ($siteLogo !== '') {
            return $siteLogo;
        }
        // Fallback: Logo aus dem Standard-Layout (visueller Baukasten, l-brand).
        try {
            $layout = \Models\Layout::default();
            $builder = json_decode((string) ($layout['builder'] ?? ''), true);
            if (is_array($builder)) {
                foreach (self::iterateBlocks($builder['rows'] ?? []) as $block) {
                    if (($block['type'] ?? '') === 'l-brand' && !empty($block['data']['logo'])) {
                        return (string) $block['data']['logo'];
                    }
                }
            }
        } catch (\Throwable) {
        }
        return '';
    }

    /** Alle Blöcke einer Zeilen/Spalten-Struktur durchlaufen. */
    private static function iterateBlocks(array $rows): \Generator
    {
        foreach ($rows as $row) {
            foreach ($row['columns'] ?? [] as $col) {
                foreach ($col['blocks'] ?? [] as $block) {
                    yield $block;
                }
            }
        }
    }
}
