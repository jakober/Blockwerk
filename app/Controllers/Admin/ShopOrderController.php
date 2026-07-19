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
            flash('success', 'Status aktualisiert.');
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
