<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Shop;
use Models\Page;
use Models\Setting;
use Models\ShopShipping;

class ShopSettingsController extends ShopAdminController
{
    public function index(): void
    {
        $this->view('admin/shop/settings', [
            'title' => 'Shop-Einstellungen',
            'active' => 'shop-settings',
            'pages' => Page::tree(),
            'shipping' => ShopShipping::all(),
            's' => [
                'enabled' => Setting::get('shop_enabled', '0'),
                'root_page' => Setting::get('shop_root_page', '0'),
                'currency' => Setting::get('shop_currency', 'EUR'),
                'symbol' => Setting::get('shop_currency_symbol', '€'),
                'pay_invoice' => Setting::get('shop_pay_invoice', '1'),
                'pay_prepay' => Setting::get('shop_pay_prepay', '1'),
                'pay_paypal' => Setting::get('shop_pay_paypal', '0'),
                'paypal_client_id' => Setting::get('shop_paypal_client_id', ''),
                'paypal_secret' => Setting::get('shop_paypal_secret', ''),
                'paypal_sandbox' => Setting::get('shop_paypal_sandbox', '1'),
                'pay_sepa' => Setting::get('shop_pay_sepa', '0'),
                'sepa_creditor_id' => Setting::get('shop_sepa_creditor_id', ''),
                'sepa_creditor_name' => Setting::get('shop_sepa_creditor_name', ''),
                'sepa_iban' => Setting::get('shop_sepa_iban', ''),
                'sepa_bic' => Setting::get('shop_sepa_bic', ''),
                'bank_info' => Setting::get('shop_bank_info', ''),
                'email' => Setting::get('shop_email', ''),
                'inv_company' => Setting::get('shop_invoice_company', ''),
                'inv_address' => Setting::get('shop_invoice_address', ''),
                'inv_email' => Setting::get('shop_invoice_email', ''),
                'inv_phone' => Setting::get('shop_invoice_phone', ''),
                'inv_website' => Setting::get('shop_invoice_website', ''),
                'inv_tax' => Setting::get('shop_invoice_tax', ''),
                'inv_bank' => Setting::get('shop_invoice_bank', ''),
                'inv_logo' => Setting::get('shop_invoice_logo', ''),
                'inv_note' => Setting::get('shop_invoice_note', ''),
                'inv_prefix' => Setting::get('shop_invoice_prefix', 'RE-'),
                'inv_start' => Setting::get('shop_invoice_start', '1'),
                'tax_mode' => Shop::taxMode(),
                'tax_rate' => Setting::get('shop_default_tax_rate', '19'),
            ],
        ]);
    }

    public function save(): void
    {
        // Der Ein/Aus-Schalter liegt in den allgemeinen Einstellungen –
        // hier NICHT anfassen (sonst würde Speichern den Shop abschalten).
        Setting::set('shop_root_page', (string) (int) ($_POST['root_page'] ?? 0));

        // Steuern: Modus + Standardsatz.
        $taxMode = ($_POST['tax_mode'] ?? '') === 'inclusive' ? 'inclusive' : 'none';
        $taxRate = (float) str_replace(',', '.', (string) ($_POST['tax_rate'] ?? '19'));
        Shop::setTaxMode($taxMode, $taxRate);
        Setting::set('shop_currency', trim($_POST['currency'] ?? 'EUR') ?: 'EUR');
        Setting::set('shop_currency_symbol', trim($_POST['symbol'] ?? '€') ?: '€');
        Setting::set('shop_pay_invoice', isset($_POST['pay_invoice']) ? '1' : '0');
        Setting::set('shop_pay_prepay', isset($_POST['pay_prepay']) ? '1' : '0');
        Setting::set('shop_pay_paypal', isset($_POST['pay_paypal']) ? '1' : '0');
        Setting::set('shop_paypal_client_id', trim($_POST['paypal_client_id'] ?? ''));
        // Secret nur überschreiben, wenn ein neuer Wert eingegeben wurde.
        $secret = trim($_POST['paypal_secret'] ?? '');
        if ($secret !== '' && !str_contains($secret, '•')) {
            Setting::set('shop_paypal_secret', $secret);
        }
        Setting::set('shop_paypal_sandbox', isset($_POST['paypal_sandbox']) ? '1' : '0');
        Setting::set('shop_pay_sepa', isset($_POST['pay_sepa']) ? '1' : '0');
        Setting::set('shop_sepa_creditor_id', trim($_POST['sepa_creditor_id'] ?? ''));
        Setting::set('shop_sepa_creditor_name', trim($_POST['sepa_creditor_name'] ?? ''));
        Setting::set('shop_sepa_iban', strtoupper(str_replace(' ', '', trim($_POST['sepa_iban'] ?? ''))));
        Setting::set('shop_sepa_bic', strtoupper(trim($_POST['sepa_bic'] ?? '')));
        Setting::set('shop_bank_info', trim($_POST['bank_info'] ?? ''));
        Setting::set('shop_email', trim($_POST['email'] ?? ''));
        // Rechnungsdaten (Absender/Kontakt auf der Rechnung).
        Setting::set('shop_invoice_company', trim($_POST['inv_company'] ?? ''));
        Setting::set('shop_invoice_address', trim($_POST['inv_address'] ?? ''));
        Setting::set('shop_invoice_email', trim($_POST['inv_email'] ?? ''));
        Setting::set('shop_invoice_phone', trim($_POST['inv_phone'] ?? ''));
        Setting::set('shop_invoice_website', trim($_POST['inv_website'] ?? ''));
        Setting::set('shop_invoice_tax', trim($_POST['inv_tax'] ?? ''));
        Setting::set('shop_invoice_bank', trim($_POST['inv_bank'] ?? ''));
        Setting::set('shop_invoice_logo', trim($_POST['inv_logo'] ?? ''));
        $invNote = trim($_POST['inv_note'] ?? '');
        if ($invNote === '' && $taxMode === 'none') {
            // Kleinunternehmer nach §19 UStG weisen keine Umsatzsteuer aus – ohne
            // eigenen Hinweistext wird der gesetzlich übliche Satz vorbefüllt.
            $invNote = 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.';
        }
        Setting::set('shop_invoice_note', $invNote);
        Setting::set('shop_invoice_prefix', trim($_POST['inv_prefix'] ?? 'RE-'));
        Setting::set('shop_invoice_start', (string) max(1, (int) ($_POST['inv_start'] ?? 1)));

        \Core\Cache::clear();
        flash('success', 'Shop-Einstellungen gespeichert.');
        redirect('/admin/shop/settings');
    }

    /* ---------- Versandarten ---------- */

    public function shippingStore(): void
    {
        $data = $this->shippingData();
        if ($data !== null) {
            ShopShipping::create($data);
            flash('success', 'Versandart hinzugefügt.');
        }
        redirect('/admin/shop/settings');
    }

    public function shippingUpdate(string $id): void
    {
        $data = $this->shippingData();
        if ($data !== null) {
            ShopShipping::update((int) $id, $data);
            flash('success', 'Versandart gespeichert.');
        }
        redirect('/admin/shop/settings');
    }

    public function shippingDelete(string $id): void
    {
        ShopShipping::delete((int) $id);
        flash('success', 'Versandart gelöscht.');
        redirect('/admin/shop/settings');
    }

    private function shippingData(): ?array
    {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash('error', 'Bitte einen Namen für die Versandart angeben.');
            return null;
        }
        // Länder → JSON-Liste (Auswahlbox liefert ein Array; Fallback: kommagetrennt). Leer = alle Länder.
        $countriesInput = $_POST['countries'] ?? [];
        $countriesRaw = is_array($countriesInput) ? $countriesInput : explode(',', (string) $countriesInput);
        $countries = array_values(array_filter(array_map('trim', $countriesRaw), static fn ($c) => $c !== ''));

        // Gewichtsstaffeln: eine Zeile je Stufe ("bis X kg" + Preis), nur
        // ausgewertet, wenn die Staffelung überhaupt aktiviert ist – so bleibt
        // "Checkbox nachträglich aus, Zeilen aber stehen gelassen" robust ohne
        // sich auf JavaScript zu verlassen (weight_tiers wird dann NULL = Pauschalpreis).
        $tiers = [];
        if (isset($_POST['tiered_pricing'])) {
            $kgs = (array) ($_POST['tier_max_kg'] ?? []);
            $prices = (array) ($_POST['tier_price'] ?? []);
            foreach ($kgs as $i => $kg) {
                $grams = (int) round(((float) str_replace(',', '.', trim((string) $kg))) * 1000);
                if ($grams > 0) {
                    $tiers[] = ['max' => $grams, 'price' => Shop::parsePrice((string) ($prices[$i] ?? '0'))];
                }
            }
            usort($tiers, static fn ($a, $b) => $a['max'] <=> $b['max']);
        }

        return [
            'name' => $name,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'price' => Shop::parsePrice((string) ($_POST['price'] ?? '0')),
            'free_from' => ($_POST['free_from'] ?? '') !== '' ? Shop::parsePrice((string) $_POST['free_from']) : null,
            'countries' => $countries !== [] ? json_encode($countries, JSON_UNESCAPED_UNICODE) : null,
            'weight_tiers' => $tiers !== [] ? json_encode($tiers) : null,
            'active' => isset($_POST['active']) ? 1 : 0,
            'position' => (int) ($_POST['position'] ?? 0),
        ];
    }
}
