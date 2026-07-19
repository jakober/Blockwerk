<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Models\ShopCategory;

class ShopCategoryController extends ShopAdminController
{
    public function index(): void
    {
        $this->view('admin/shop/categories', [
            'title' => 'Shop-Kategorien',
            'active' => 'shop-categories',
            'categories' => ShopCategory::tree(),
        ]);
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(string $id): void
    {
        $cat = ShopCategory::find((int) $id) ?? $this->abort();
        $this->form($cat);
    }

    private function form(?array $cat): void
    {
        // Beim Bearbeiten sich selbst (und Nachfahren) nicht als Elternteil anbieten.
        $exclude = $cat ? ShopCategory::withDescendants((int) $cat['id']) : [];
        $parents = array_filter(ShopCategory::tree(), fn ($c) => !in_array((int) $c['id'], $exclude, true));
        $this->view('admin/shop/category-form', [
            'title' => $cat ? 'Kategorie bearbeiten' : 'Neue Kategorie',
            'active' => 'shop-categories',
            'category' => $cat,
            'parents' => $parents,
        ]);
    }

    public function store(): void
    {
        $data = $this->validated('/admin/shop/categories/new');
        ShopCategory::create($data);
        flash('success', 'Kategorie angelegt.');
        redirect('/admin/shop/categories');
    }

    public function update(string $id): void
    {
        $cat = ShopCategory::find((int) $id) ?? $this->abort();
        $data = $this->validated('/admin/shop/categories/' . $cat['id'] . '/edit');
        ShopCategory::update((int) $cat['id'], $data);
        flash('success', 'Kategorie gespeichert.');
        redirect('/admin/shop/categories');
    }

    public function delete(string $id): void
    {
        ShopCategory::delete((int) $id);
        flash('success', 'Kategorie gelöscht. Produkte bleiben erhalten (ohne Kategorie).');
        redirect('/admin/shop/categories');
    }

    private function validated(string $backTo): array
    {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash('error', 'Bitte einen Kategorienamen angeben.');
            redirect($backTo);
        }
        return [
            'name' => $name,
            'slug' => trim($_POST['slug'] ?? ''),
            'parent_id' => (int) ($_POST['parent_id'] ?? 0),
            'description' => trim($_POST['description'] ?? '') ?: null,
            'image' => trim($_POST['image'] ?? '') ?: null,
            'position' => (int) ($_POST['position'] ?? 0),
        ];
    }

    private function abort(): never
    {
        flash('error', 'Kategorie nicht gefunden.');
        redirect('/admin/shop/categories');
    }
}
