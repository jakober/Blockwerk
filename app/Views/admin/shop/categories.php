<div class="page-actions" style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
    <a class="btn btn-primary" href="<?= e(url('/admin/shop/categories/new')) ?>">+ Neue Kategorie</a>
    <a class="btn btn-ghost" href="<?= e(url('/admin/shop/products')) ?>">Zu den Produkten</a>
</div>

<div class="card">
    <?php if (empty($categories)): ?>
        <p class="muted">Noch keine Kategorien. Kategorien strukturieren deinen Shop (mit Unterkategorien möglich).</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Name</th><th>Slug</th><th>Produkte</th><th class="actions-col">Aktionen</th></tr></thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td>
                            <span class="tree-indent" style="--depth:<?= (int) $c['depth'] ?>"></span>
                            <a href="<?= e(url('/admin/shop/categories/' . $c['id'] . '/edit')) ?>"><strong><?= e($c['name']) ?></strong></a>
                        </td>
                        <td><code>/<?= e($c['slug']) ?></code></td>
                        <td class="muted"><?= \Models\ShopCategory::productCount((int) $c['id']) ?></td>
                        <td class="actions-col">
                            <a class="btn btn-small" href="<?= e(url('/admin/shop/categories/' . $c['id'] . '/edit')) ?>">Bearbeiten</a>
                            <form method="post" action="<?= e(url('/admin/shop/categories/' . $c['id'] . '/delete')) ?>" class="inline" data-confirm="Kategorie „<?= e($c['name']) ?>“ löschen? Produkte bleiben erhalten." data-confirm-danger data-confirm-ok="Löschen">
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
