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
        // Andere Produkte für Cross-Selling/Zubehör (ohne das aktuelle).
        $others = array_values(array_filter(
            ShopProduct::all(),
            static fn ($p) => $product === null || (int) $p['id'] !== (int) $product['id']
        ));
        $this->view('admin/shop/product-form', [
            'title' => $product ? 'Produkt bearbeiten' : 'Neues Produkt',
            'active' => 'shop-products',
            'product' => $product,
            'categories' => ShopCategory::tree(),
            'others' => $others,
            'tiers' => $product ? ShopProduct::tiers($product) : [],
            'optionGroups' => $product ? ShopProduct::options($product) : [],
            'crossIds' => $product ? array_map('intval', json_decode((string) ($product['cross_sell'] ?? ''), true) ?: []) : [],
            'accIds' => $product ? array_map('intval', json_decode((string) ($product['accessories'] ?? ''), true) ?: []) : [],
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
            'tier_prices' => $this->buildTiers(),
            'options' => $this->buildOptions(),
            'cross_sell' => $this->buildIdList('cross_sell'),
            'accessories' => $this->buildIdList('accessories'),
        ];
    }

    /** Staffelpreise aus tier_min[]/tier_price[] als JSON [{min,price(cents)}]. */
    private function buildTiers(): ?string
    {
        $mins = (array) ($_POST['tier_min'] ?? []);
        $prices = (array) ($_POST['tier_price'] ?? []);
        $tiers = [];
        foreach ($mins as $i => $min) {
            $min = (int) $min;
            $price = Shop::parsePrice((string) ($prices[$i] ?? '0'));
            if ($min > 1 && $price > 0) {
                $tiers[] = ['min' => $min, 'price' => $price];
            }
        }
        usort($tiers, static fn ($a, $b) => $a['min'] <=> $b['min']);
        return $tiers === [] ? null : json_encode($tiers, JSON_UNESCAPED_UNICODE);
    }

    /** Eigenschaften/Varianten aus dem versteckten JSON-Feld sanitisieren. */
    private function buildOptions(): ?string
    {
        $raw = json_decode((string) ($_POST['options'] ?? ''), true);
        if (!is_array($raw)) {
            return null;
        }
        $groups = [];
        foreach ($raw as $g) {
            $name = trim((string) ($g['name'] ?? ''));
            $choices = [];
            foreach ((array) ($g['choices'] ?? []) as $c) {
                $label = trim((string) ($c['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $choices[] = ['label' => $label, 'diff' => Shop::parsePrice((string) ($c['diff'] ?? '0'))];
            }
            if ($name !== '' && $choices !== []) {
                $groups[] = ['name' => $name, 'choices' => $choices];
            }
        }
        return $groups === [] ? null : json_encode($groups, JSON_UNESCAPED_UNICODE);
    }

    private function buildIdList(string $field): ?string
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) ($_POST[$field] ?? [])),
            static fn ($id) => $id > 0
        )));
        return $ids === [] ? null : json_encode($ids);
    }

    private function abort(): never
    {
        flash('error', 'Produkt nicht gefunden.');
        redirect('/admin/shop/products');
    }
}
