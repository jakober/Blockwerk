<?php
$statusLabels = ['new' => 'Neu', 'paid' => 'Bezahlt', 'shipped' => 'Versendet', 'cancelled' => 'Storniert'];
$statusBadge = ['new' => 'badge-amber', 'paid' => 'badge-green', 'shipped' => 'badge-green', 'cancelled' => 'badge'];
?>
<div class="page-actions" style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn btn-small <?= $filter === '' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('/admin/shop/orders')) ?>">Alle</a>
    <?php foreach ($statusLabels as $k => $label): ?>
        <a class="btn btn-small <?= $filter === $k ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('/admin/shop/orders?status=' . $k)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <?php if (empty($orders)): ?>
        <p class="muted">Noch keine Bestellungen.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Nr.</th><th>Datum</th><th>Kunde</th><th>Summe</th><th>Zahlung</th><th>Status</th><th class="actions-col"></th></tr></thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><a href="<?= e(url('/admin/shop/orders/' . $o['id'])) ?>"><strong><?= e($o['number']) ?></strong></a></td>
                        <td class="muted small"><?= e(format_date_de($o['created_at'], true)) ?></td>
                        <td><?= e(trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?: $o['email']) ?></td>
                        <td><?= e(\Core\Shop::formatPrice((int) $o['total'])) ?></td>
                        <td class="small"><?= e($o['payment_method'] ?? '–') ?> · <?= (int) ($o['payment_status'] === 'paid') ? '<span class="badge badge-green">bezahlt</span>' : '<span class="badge badge-amber">offen</span>' ?></td>
                        <td><span class="badge <?= $statusBadge[$o['status']] ?? 'badge' ?>"><?= e($statusLabels[$o['status']] ?? $o['status']) ?></span></td>
                        <td class="actions-col"><a class="btn btn-small" href="<?= e(url('/admin/shop/orders/' . $o['id'])) ?>">Ansehen</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
