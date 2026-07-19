<div class="page-actions" style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
    <a class="btn btn-primary" href="<?= e(url('/admin/shop/products/new')) ?>">+ Neues Produkt</a>
    <a class="btn btn-ghost" href="<?= e(url('/admin/shop/categories')) ?>">Kategorien verwalten</a>
</div>

<div class="card">
    <?php if (empty($products)): ?>
        <p class="muted">Noch keine Produkte. Lege dein erstes Produkt an!</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Produkt</th><th>Kategorie</th><th>Preis</th><th>Bestand</th><th>Status</th><th class="actions-col">Aktionen</th></tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td data-label="Produkt">
                            <?php if (!empty($p['image'])): ?><img src="<?= e($p['image']) ?>" alt="" style="width:34px;height:34px;object-fit:cover;border-radius:6px;vertical-align:middle;margin-right:8px"><?php endif; ?>
                            <a href="<?= e(url('/admin/shop/products/' . $p['id'] . '/edit')) ?>"><strong><?= e($p['name']) ?></strong></a>
                            <?php if (!empty($p['sku'])): ?><span class="muted small"> · <?= e($p['sku']) ?></span><?php endif; ?>
                        </td>
                        <td class="muted" data-label="Kategorie"><?= e($cats[(int) ($p['category_id'] ?? 0)] ?? '–') ?></td>
                        <td data-label="Preis"><?= e(\Core\Shop::formatPrice((int) $p['price'])) ?></td>
                        <td data-label="Bestand"><?= $p['stock'] === null ? '<span class="muted">∞</span>' : (int) $p['stock'] ?></td>
                        <td data-label="Status">
                            <?= (int) $p['active'] ? '<span class="badge badge-green">Aktiv</span>' : '<span class="badge">Inaktiv</span>' ?>
                            <?= (int) $p['featured'] ? '<span class="badge badge-orange">★</span>' : '' ?>
                        </td>
                        <td class="actions-col">
                            <a class="btn btn-small" href="<?= e(url('/admin/shop/products/' . $p['id'] . '/edit')) ?>">Bearbeiten</a>
                            <form method="post" action="<?= e(url('/admin/shop/products/' . $p['id'] . '/delete')) ?>" class="inline" data-confirm="Produkt „<?= e($p['name']) ?>“ wirklich löschen?" data-confirm-danger data-confirm-ok="Löschen">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger">Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
