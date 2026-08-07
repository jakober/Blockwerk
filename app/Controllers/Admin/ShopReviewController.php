<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Models\ShopProduct;
use Models\ShopReview;

class ShopReviewController extends ShopAdminController
{
    public function index(): void
    {
        $products = [];
        foreach (ShopProduct::all() as $p) {
            $products[(int) $p['id']] = $p['name'];
        }
        $this->view('admin/shop/reviews', [
            'title' => 'Bewertungen',
            'active' => 'shop-reviews',
            'reviews' => ShopReview::all(),
            'products' => $products,
        ]);
    }

    public function approve(string $id): void
    {
        ShopReview::approve((int) $id);
        flash('success', 'Bewertung freigegeben.');
        redirect('/admin/shop/reviews');
    }

    public function delete(string $id): void
    {
        ShopReview::delete((int) $id);
        flash('success', 'Bewertung gelöscht.');
        redirect('/admin/shop/reviews');
    }
}
