<?php
declare(strict_types=1);

namespace Core;

use Models\Setting;
use Models\ShopOrder;

/**
 * Zentrale Shop-E-Mails (Bestellbestätigung an den Besteller, Benachrichtigung
 * an den Shop-Betreiber, Status-Änderung an den Kunden). Nutzt Core\Mailer und
 * schlägt nie fatal fehl – ein Mailproblem darf Bestellung/Statuswechsel nie
 * blockieren.
 */
class ShopMail
{
    /** Bestellbestätigung an den Besteller. */
    public static function confirmation(array $order, array $items): void
    {
        if (!filter_var($order['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $name = Shop::invoiceName();
        $body = 'Hallo ' . trim((string) ($order['first_name'] ?? '')) . ",\n\n"
            . 'vielen Dank für deine Bestellung ' . $order['number'] . ' bei ' . $name . ".\n\n"
            . self::itemsBlock($order, $items) . "\n"
            . self::bankBlockIfPrepay($order)
            . "Den Status deiner Bestellung kannst du hier jederzeit einsehen:\n" . self::orderUrl((string) $order['token']) . "\n\n"
            . "Herzliche Grüße\n" . $name;
        self::send((string) $order['email'], 'Bestellbestätigung ' . $order['number'] . ' – ' . $name, $body);
    }

    /** Benachrichtigung an die im Shop hinterlegte Kontakt-E-Mail. */
    public static function shopNotification(array $order, array $items): void
    {
        $to = trim((string) Setting::get('shop_email', ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $addr = trim(((string) ($order['first_name'] ?? '')) . ' ' . ((string) ($order['last_name'] ?? '')));
        $body = 'Neue Bestellung ' . $order['number'] . " ist eingegangen.\n\n"
            . 'Kunde: ' . $addr . ' <' . $order['email'] . ">\n"
            . (!empty($order['phone']) ? 'Telefon: ' . $order['phone'] . "\n" : '')
            . "\n" . self::itemsBlock($order, $items)
            . "\nZahlungsart: " . ((string) ($order['payment_method'] ?? '-')) . "\n"
            . 'Im Backend ansehen:' . "\n" . self::host() . url('/admin/shop/orders/' . ((int) ($order['id'] ?? 0))) . "\n";
        self::send($to, 'Neue Bestellung ' . $order['number'], $body);
    }

    /** Status-Änderung an den Kunden (paid/shipped/cancelled). */
    public static function statusUpdate(array $order): void
    {
        if (!filter_var($order['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $status = (string) ($order['status'] ?? '');
        $intro = match ($status) {
            'paid' => 'deine Zahlung ist bei uns eingegangen – vielen Dank!',
            'shipped' => 'deine Bestellung wurde versendet und ist auf dem Weg zu dir.',
            'cancelled' => 'deine Bestellung wurde storniert.',
            default => 'der Status deiner Bestellung hat sich geändert.',
        };
        $name = Shop::invoiceName();
        $label = ShopOrder::statusLabel($status);
        $body = 'Hallo ' . trim((string) ($order['first_name'] ?? '')) . ",\n\n"
            . $intro . "\n\n"
            . 'Bestellung: ' . $order['number'] . "\n"
            . 'Neuer Status: ' . $label . "\n\n"
            . "Details ansehen:\n" . self::orderUrl((string) $order['token']) . "\n\n"
            . "Herzliche Grüße\n" . $name;
        self::send((string) $order['email'], 'Bestellung ' . $order['number'] . ': ' . $label, $body);
    }

    /**
     * Rechnung als E-Mail an den Kunden (HTML-Rechnung im A4-Layout + Text-
     * Fallback und Link zur Online-Rechnung). Rückgabe: null bei Erfolg, sonst
     * eine Fehlermeldung (für die Admin-Rückmeldung).
     */
    public static function invoice(array $order, array $items): ?string
    {
        if (!filter_var($order['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            return 'Für diese Bestellung ist keine gültige E-Mail-Adresse hinterlegt.';
        }
        $name = Shop::invoiceName();
        $html = View::fetch('shop/invoice', ['order' => $order, 'items' => $items, 'mode' => 'email']);
        $text = 'Hallo ' . trim((string) ($order['first_name'] ?? '')) . ",\n\n"
            . 'anbei deine Rechnung ' . $order['number'] . ' zu deiner Bestellung bei ' . $name . ".\n\n"
            . self::itemsBlock($order, $items) . "\n"
            . self::bankBlockIfPrepay($order)
            . "Die Rechnung kannst du hier auch online ansehen und als PDF speichern:\n"
            . self::orderUrl((string) $order['token']) . "/rechnung\n\n"
            . "Herzliche Grüße\n" . $name;
        try {
            $err = Mailer::send((string) $order['email'], 'Rechnung ' . $order['number'] . ' – ' . $name, $text, null, $html);
        } catch (\Throwable) {
            $err = 'Der E-Mail-Versand ist fehlgeschlagen.';
        }
        return $err;
    }

    private static function itemsBlock(array $order, array $items): string
    {
        $out = "Deine Bestellung:\n";
        foreach ($items as $it) {
            $out .= '- ' . (int) $it['qty'] . '× ' . $it['name'] . ': '
                . Shop::formatPrice((int) $it['price'] * (int) $it['qty']) . "\n";
        }
        $out .= 'Zwischensumme: ' . Shop::formatPrice((int) $order['subtotal']) . "\n";
        $out .= 'Versand: ' . Shop::formatPrice((int) $order['shipping_cost']) . "\n";
        $out .= 'Gesamt: ' . Shop::formatPrice((int) $order['total']) . "\n";
        return $out;
    }

    private static function bankBlockIfPrepay(array $order): string
    {
        if (($order['payment_method'] ?? '') !== 'prepay') {
            return '';
        }
        $bank = trim((string) Setting::get('shop_bank_info', ''));
        if ($bank === '') {
            return '';
        }
        return "Bitte überweise den Betrag (Verwendungszweck " . $order['number'] . ") auf folgendes Konto:\n" . $bank . "\n\n";
    }

    private static function orderUrl(string $token): string
    {
        return self::host() . url('/' . trim(Shop::rootSlug(), '/') . '/bestellung/' . $token);
    }

    private static function host(): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    }

    private static function send(string $to, string $subject, string $body): void
    {
        try {
            Mailer::send($to, $subject, $body);
        } catch (\Throwable) {
        }
    }
}
