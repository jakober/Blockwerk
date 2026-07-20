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
        ]);
    }

    /** Druckbare Rechnung (eigenständige HTML-Seite, „als PDF speichern" im Browser). */
    public function invoice(string $id): void
    {
        $order = ShopOrder::find((int) $id) ?? $this->abort();
        echo \Core\View::fetch('shop/invoice', [
            'order' => $order,
            'items' => ShopOrder::items((int) $order['id']),
        ]);
    }

    /** Rechnung als E-Mail an den Kunden senden. */
    public function mailInvoice(string $id): void
    {
        $order = ShopOrder::find((int) $id) ?? $this->abort();
        $err = \Core\ShopMail::invoice($order, ShopOrder::items((int) $order['id']));
        if ($err === null) {
            flash('success', 'Rechnung wurde an ' . $order['email'] . ' gesendet.');
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
            if ($status === 'paid') {
                ShopOrder::setPaid((int) $order['id']);
            } else {
                ShopOrder::setStatus((int) $order['id'], $status);
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
