<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Models\ShopOrder;

class ShopOrderController extends ShopAdminController
{
    public function index(): void
    {
        $filter = $_GET['status'] ?? '';
        $this->view('admin/shop/orders', [
            'title' => 'Bestellungen',
            'active' => 'shop-orders',
            'orders' => ShopOrder::all($filter),
            'filter' => $filter,
        ]);
    }

    public function show(string $id): void
    {
        $order = ShopOrder::find((int) $id) ?? $this->abort();
        $this->view('admin/shop/order-detail', [
            'title' => 'Bestellung ' . $order['number'],
            'active' => 'shop-orders',
            'order' => $order,
            'items' => ShopOrder::items((int) $order['id']),
            'invoice' => \Models\Invoice::findByOrder((int) $order['id']),
        ]);
    }

    /** Rechnung erstellen: vergibt eine fortlaufende Rechnungsnummer. */
    public function createInvoice(string $id): void
    {
        $order = ShopOrder::find((int) $id) ?? $this->abort();
        $invoice = \Models\Invoice::createForOrder((int) $order['id']);
        flash('success', 'Rechnung ' . $invoice['number'] . ' wurde erstellt.');
        redirect('/admin/shop/orders/' . $order['id']);
    }

    /** Erstellte Rechnung als PDF ausgeben (Anzeigen/Herunterladen). */
    public function invoice(string $id): void
    {
        $order = ShopOrder::find((int) $id) ?? $this->abort();
        $invoice = \Models\Invoice::findByOrder((int) $order['id']);
        if ($invoice === null) {
            flash('error', 'Für diese Bestellung wurde noch keine Rechnung erstellt. Bitte zuerst „Rechnung erstellen" drücken.');
            redirect('/admin/shop/orders/' . $order['id']);
        }
        $pdf = \Core\InvoicePdf::render($order, ShopOrder::items((int) $order['id']), $invoice);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . \Core\InvoicePdf::filename($invoice) . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: no-store');
        echo $pdf;
    }

    /** Rechnung (PDF im Anhang) an den Kunden senden. Erstellt sie bei Bedarf. */
    public function mailInvoice(string $id): void
    {
        $order = ShopOrder::find((int) $id) ?? $this->abort();
        $err = \Core\ShopMail::invoice($order, ShopOrder::items((int) $order['id']));
        if ($err === null) {
            flash('success', 'Rechnung als PDF an ' . $order['email'] . ' gesendet.');
        } else {
            flash('error', 'Rechnung konnte nicht gesendet werden: ' . $err);
        }
        redirect('/admin/shop/orders/' . $order['id']);
    }

    public function setStatus(string $id): void
    {
        $order = ShopOrder::find((int) $id) ?? $this->abort();
        $status = $_POST['status'] ?? '';
        $allowed = ['new', 'paid', 'shipped', 'cancelled'];
        if (in_array($status, $allowed, true)) {
            $wasCancelled = $order['status'] === 'cancelled';
            $nowCancelled = $status === 'cancelled';
            if ($status === 'paid') {
                ShopOrder::setPaid((int) $order['id']);
            } else {
                ShopOrder::setStatus((int) $order['id'], $status);
            }
            // Lagerbestand zurückbuchen, wenn eine Bestellung storniert wird –
            // und symmetrisch wieder abziehen, falls eine Stornierung rückgängig
            // gemacht wird, damit der Bestand nach mehrfachem Wechseln stimmt.
            if ($nowCancelled && !$wasCancelled) {
                $this->adjustStock((int) $order['id'], 1);
            } elseif (!$nowCancelled && $wasCancelled) {
                $this->adjustStock((int) $order['id'], -1);
            }
            // Tracking-Nummer/-URL mitspeichern, falls im selben Formular mitgeschickt.
            if (isset($_POST['tracking_number']) || isset($_POST['tracking_url'])) {
                ShopOrder::setTracking(
                    (int) $order['id'],
                    trim((string) ($_POST['tracking_number'] ?? '')) ?: null,
                    trim((string) ($_POST['tracking_url'] ?? '')) ?: null
                );
            }
            // Den Kunden über die Statusänderung informieren (paid/shipped/cancelled).
            $mailed = false;
            if (in_array($status, ['paid', 'shipped', 'cancelled'], true) && empty($_POST['no_mail'])) {
                $updated = ShopOrder::find((int) $order['id']);
                if ($updated !== null) {
                    \Core\ShopMail::statusUpdate($updated);
                    $mailed = true;
                }
            }
            flash('success', 'Status aktualisiert.' . ($mailed ? ' Der Kunde wurde per E-Mail benachrichtigt.' : ''));
        }
        redirect('/admin/shop/orders/' . $order['id']);
    }

    /** Lagerbestand aller Positionen einer Bestellung anpassen: $sign=1 zurückbuchen, -1 erneut abziehen. */
    private function adjustStock(int $orderId, int $sign): void
    {
        foreach (ShopOrder::items($orderId) as $it) {
            if (empty($it['product_id'])) {
                continue;
            }
            if ($sign > 0) {
                \Models\ShopProduct::increaseStock((int) $it['product_id'], (int) $it['qty']);
            } else {
                \Models\ShopProduct::decreaseStock((int) $it['product_id'], (int) $it['qty']);
            }
        }
    }

    /**
     * Bestellung erstatten: bei PayPal-Zahlung über die PayPal-API (ganz oder
     * teilweise), sonst nur eine Status-Änderung (Überweisung/Rechnung läuft
     * außerhalb des Systems). Bucht in beiden Fällen den Lagerbestand zurück.
     */
    public function refund(string $id): void
    {
        $order = ShopOrder::find((int) $id) ?? $this->abort();
        $amountInput = trim((string) ($_POST['amount'] ?? ''));
        $amountCents = $amountInput !== '' ? \Core\Shop::parsePrice($amountInput) : null;

        if (($order['payment_method'] ?? '') === 'paypal' && !empty($order['paypal_order_id'])) {
            [$ok, $err] = \Core\PayPal::refund((string) $order['paypal_order_id'], $amountCents, (string) $order['currency']);
            if (!$ok) {
                flash('error', 'Rückerstattung über PayPal fehlgeschlagen: ' . ($err ?? 'Unbekannter Fehler'));
                redirect('/admin/shop/orders/' . $order['id']);
            }
        }

        $wasCancelled = $order['status'] === 'cancelled';
        ShopOrder::setStatus((int) $order['id'], 'refunded');
        if (!$wasCancelled) {
            $this->adjustStock((int) $order['id'], 1);
        }
        $updated = ShopOrder::find((int) $order['id']);
        if ($updated !== null && empty($_POST['no_mail'])) {
            \Core\ShopMail::statusUpdate($updated);
        }
        flash('success', 'Bestellung wurde als erstattet markiert' . (($amountCents !== null) ? ' (' . \Core\Shop::formatPrice($amountCents) . ')' : '') . '.');
        redirect('/admin/shop/orders/' . $order['id']);
    }

    public function delete(string $id): void
    {
        ShopOrder::delete((int) $id);
        flash('success', 'Bestellung gelöscht.');
        redirect('/admin/shop/orders');
    }

    private function abort(): never
    {
        flash('error', 'Bestellung nicht gefunden.');
        redirect('/admin/shop/orders');
    }
}
