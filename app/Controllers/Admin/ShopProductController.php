<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Shop;
use Models\ShopCategory;
use Models\ShopProduct;

class ShopProductController extends ShopAdminController
{
    public function index(): void
    {
        $cats = [];
        foreach (ShopCategory::all() as $c) {
            $cats[(int) $c['id']] = $c['name'];
        }
        $this->view('admin/shop/products', [
            'title' => 'Produkte',
            'active' => 'shop-products',
            'products' => ShopProduct::all(),
            'cats' => $cats,
        ]);
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(string $id): void
    {
        $product = ShopProduct::find((int) $id) ?? $this->abort();
        $this->form($product);
    }

    private function form(?array $product): void
    {
        $this->view('admin/shop/product-form', [
            'title' => $product ? 'Produkt bearbeiten' : 'Neues Produkt',
            'active' => 'shop-products',
            'product' => $product,
            'categories' => ShopCategory::tree(),
        ]);
    }

    public function store(): void
    {
        $data = $this->validated('/admin/shop/products/new');
        ShopProduct::create($data);
        flash('success', 'Produkt angelegt.');
        redirect('/admin/shop/products');
    }

    public function update(string $id): void
    {
        $product = ShopProduct::find((int) $id) ?? $this->abort();
        $data = $this->validated('/admin/shop/products/' . $product['id'] . '/edit');
        ShopProduct::update((int) $product['id'], $data);
        flash('success', 'Produkt gespeichert.');
        redirect('/admin/shop/products');
    }

    public function delete(string $id): void
    {
        ShopProduct::delete((int) $id);
        flash('success', 'Produkt gelöscht.');
        redirect('/admin/shop/products');
    }

    private function validated(string $backTo): array
    {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash('error', 'Bitte einen Produktnamen angeben.');
            redirect($backTo);
        }
        return [
            'name' => $name,
            'slug' => trim($_POST['slug'] ?? ''),
            'sku' => trim($_POST['sku'] ?? '') ?: null,
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'price' => Shop::parsePrice((string) ($_POST['price'] ?? '0')),
            'compare_price' => ($_POST['compare_price'] ?? '') !== '' ? Shop::parsePrice((string) $_POST['compare_price']) : null,
            'short_desc' => trim($_POST['short_desc'] ?? '') ?: null,
            'description' => (string) ($_POST['description'] ?? '') ?: null,
            'image' => trim($_POST['image'] ?? '') ?: null,
            'gallery' => trim($_POST['gallery'] ?? '') ?: null,
            'stock' => ($_POST['stock'] ?? '') !== '' ? (int) $_POST['stock'] : null,
            'weight' => ($_POST['weight'] ?? '') !== '' ? (int) $_POST['weight'] : null,
            'active' => isset($_POST['active']) ? 1 : 0,
            'featured' => isset($_POST['featured']) ? 1 : 0,
            'position' => (int) ($_POST['position'] ?? 0),
        ];
    }

    private function abort(): never
    {
        flash('error', 'Produkt nicht gefunden.');
        redirect('/admin/shop/products');
    }
}
