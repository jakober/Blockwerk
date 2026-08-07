<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Shop;
use Models\ShopCoupon;

class ShopCouponController extends ShopAdminController
{
    public function index(): void
    {
        $this->view('admin/shop/coupons', [
            'title' => 'Gutscheine',
            'active' => 'shop-coupons',
            'coupons' => ShopCoupon::all(),
        ]);
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(string $id): void
    {
        $coupon = ShopCoupon::find((int) $id) ?? $this->abort();
        $this->form($coupon);
    }

    private function form(?array $coupon): void
    {
        $this->view('admin/shop/coupon-form', [
            'title' => $coupon ? 'Gutschein bearbeiten' : 'Neuer Gutschein',
            'active' => 'shop-coupons',
            'coupon' => $coupon,
        ]);
    }

    public function store(): void
    {
        $data = $this->validated('/admin/shop/coupons/new');
        ShopCoupon::create($data);
        flash('success', 'Gutschein angelegt.');
        redirect('/admin/shop/coupons');
    }

    public function update(string $id): void
    {
        $coupon = ShopCoupon::find((int) $id) ?? $this->abort();
        $data = $this->validated('/admin/shop/coupons/' . $coupon['id'] . '/edit');
        ShopCoupon::update((int) $coupon['id'], $data);
        flash('success', 'Gutschein gespeichert.');
        redirect('/admin/shop/coupons');
    }

    public function delete(string $id): void
    {
        ShopCoupon::delete((int) $id);
        flash('success', 'Gutschein gelöscht.');
        redirect('/admin/shop/coupons');
    }

    private function validated(string $backTo): array
    {
        $code = trim($_POST['code'] ?? '');
        if ($code === '') {
            flash('error', 'Bitte einen Gutscheincode angeben.');
            redirect($backTo);
        }
        $value = trim((string) ($_POST['value'] ?? '0'));
        $type = ($_POST['type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
        return [
            'code' => $code,
            'type' => $type,
            'value' => $type === 'fixed' ? Shop::parsePrice($value) : max(0, min(100, (int) $value)),
            'min_subtotal' => ($_POST['min_subtotal'] ?? '') !== '' ? Shop::parsePrice((string) $_POST['min_subtotal']) : null,
            'starts_at' => trim($_POST['starts_at'] ?? '') !== '' ? trim($_POST['starts_at']) . ':00' : null,
            'ends_at' => trim($_POST['ends_at'] ?? '') !== '' ? trim($_POST['ends_at']) . ':00' : null,
            'usage_limit' => ($_POST['usage_limit'] ?? '') !== '' ? (int) $_POST['usage_limit'] : null,
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
    }

    private function abort(): never
    {
        flash('error', 'Gutschein nicht gefunden.');
        redirect('/admin/shop/coupons');
    }
}
