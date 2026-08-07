<?php $fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c); ?>
<div class="page-actions" style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
    <a class="btn btn-primary" href="<?= e(url('/admin/shop/coupons/new')) ?>">+ Neuer Gutschein</a>
    <a class="btn btn-ghost" href="<?= e(url('/admin/shop/products')) ?>">Zu den Produkten</a>
</div>

<div class="card">
    <?php if (empty($coupons)): ?>
        <p class="muted">Noch keine Gutscheine.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Code</th><th>Rabatt</th><th>Mindestbestellwert</th><th>Gültigkeit</th><th>Genutzt</th><th>Status</th><th class="actions-col">Aktionen</th></tr></thead>
            <tbody>
                <?php foreach ($coupons as $c): ?>
                    <tr>
                        <td data-label="Code"><a href="<?= e(url('/admin/shop/coupons/' . $c['id'] . '/edit')) ?>"><strong><?= e($c['code']) ?></strong></a></td>
                        <td data-label="Rabatt"><?= $c['type'] === 'fixed' ? e($fmt($c['value'])) : (int) $c['value'] . ' %' ?></td>
                        <td class="muted" data-label="Mindestbestellwert"><?= $c['min_subtotal'] !== null ? e($fmt($c['min_subtotal'])) : '–' ?></td>
                        <td class="muted" data-label="Gültigkeit">
                            <?= !empty($c['starts_at']) ? e(format_date_de($c['starts_at'])) : '–' ?>
                            – <?= !empty($c['ends_at']) ? e(format_date_de($c['ends_at'])) : 'unbegrenzt' ?>
                        </td>
                        <td class="muted" data-label="Genutzt"><?= (int) $c['used_count'] ?><?= $c['usage_limit'] !== null ? ' / ' . (int) $c['usage_limit'] : '' ?></td>
                        <td data-label="Status"><?= (int) $c['active'] ? '<span class="badge badge-green">Aktiv</span>' : '<span class="badge">Inaktiv</span>' ?></td>
                        <td class="actions-col">
                            <a class="btn btn-small" href="<?= e(url('/admin/shop/coupons/' . $c['id'] . '/edit')) ?>">Bearbeiten</a>
                            <form method="post" action="<?= e(url('/admin/shop/coupons/' . $c['id'] . '/delete')) ?>" class="inline" data-confirm="Gutschein „<?= e($c['code']) ?>“ löschen?" data-confirm-danger data-confirm-ok="Löschen">
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
