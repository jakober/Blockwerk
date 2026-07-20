<?php
$statusLabels = ['new' => 'Neu', 'paid' => 'Bezahlt', 'shipped' => 'Versendet', 'cancelled' => 'Storniert'];
$fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c);
?>
<div class="page-actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <a class="btn btn-ghost" href="<?= e(url('/admin/shop/orders')) ?>">← Alle Bestellungen</a>
    <a class="btn" href="<?= e(url('/admin/shop/orders/' . $order['id'] . '/invoice')) ?>" target="_blank" rel="noopener">🧾 Rechnung ansehen</a>
    <form method="post" action="<?= e(url('/admin/shop/orders/' . $order['id'] . '/invoice-mail')) ?>" class="inline" data-confirm="Rechnung per E-Mail an <?= e($order['email']) ?> senden?" data-confirm-ok="Senden">
        <?= csrf_field() ?>
        <button type="submit" class="btn">✉️ Rechnung per E-Mail senden</button>
    </form>
</div>

<div class="editor-grid">
    <div>
        <div class="card">
            <h2>Bestellung <?= e($order['number']) ?></h2>
            <p class="muted small"><?= e(format_date_de($order['created_at'], true)) ?></p>
            <table class="table">
                <thead><tr><th>Artikel</th><th>Einzelpreis</th><th>Menge</th><th>Summe</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><strong><?= e($it['name']) ?></strong><?php if (!empty($it['sku'])): ?> <span class="muted small">· <?= e($it['sku']) ?></span><?php endif; ?></td>
                            <td><?= e($fmt($it['price'])) ?></td>
                            <td><?= (int) $it['qty'] ?></td>
                            <td><?= e($fmt((int) $it['price'] * (int) $it['qty'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="3" style="text-align:right">Zwischensumme</td><td><?= e($fmt($order['subtotal'])) ?></td></tr>
                    <tr><td colspan="3" style="text-align:right">Versand (<?= e($order['shipping_method'] ?? '–') ?>)</td><td><?= e($fmt($order['shipping_cost'])) ?></td></tr>
                    <tr><td colspan="3" style="text-align:right"><strong>Gesamt</strong></td><td><strong><?= e($fmt($order['total'])) ?></strong></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <aside class="card">
        <h3>Status</h3>
        <form method="post" action="<?= e(url('/admin/shop/orders/' . $order['id'] . '/status')) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <select name="status">
                    <?php foreach ($statusLabels as $k => $label): ?>
                        <option value="<?= $k ?>" <?= $order['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="checkbox-group" style="font-size:13px"><input type="checkbox" name="no_mail" value="1"> Kunde nicht per E-Mail benachrichtigen</label>
            <p class="muted small" style="margin:4px 0 8px">Bei „Bezahlt", „Versendet" oder „Storniert" erhält der Kunde standardmäßig eine E-Mail mit dem neuen Status.</p>
            <button type="submit" class="btn btn-primary btn-small">Status setzen</button>
        </form>

        <h3 style="margin-top:20px">Zahlung</h3>
        <p class="small"><?= e($order['payment_method'] ?? '–') ?> ·
            <?= $order['payment_status'] === 'paid' ? '<span class="badge badge-green">bezahlt</span>' : '<span class="badge badge-amber">offen</span>' ?>
            <?php if (!empty($order['paypal_order_id'])): ?><br><span class="muted small">PayPal: <?= e($order['paypal_order_id']) ?></span><?php endif; ?>
        </p>

        <h3 style="margin-top:20px">Kunde</h3>
        <p class="small">
            <?= e(trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))) ?><br>
            <?php if (!empty($order['company'])): ?><?= e($order['company']) ?><br><?php endif; ?>
            <?= e($order['street'] ?? '') ?><br>
            <?= e(trim(($order['zip'] ?? '') . ' ' . ($order['city'] ?? ''))) ?><br>
            <?= e($order['country'] ?? '') ?><br>
            <a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a>
            <?php if (!empty($order['phone'])): ?><br><?= e($order['phone']) ?><?php endif; ?>
        </p>
        <?php if (!empty($order['note'])): ?>
            <h3 style="margin-top:20px">Anmerkung</h3>
            <p class="small"><?= nl2br(e($order['note'])) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/admin/shop/orders/' . $order['id'] . '/delete')) ?>" class="inline" data-confirm="Bestellung endgültig löschen?" data-confirm-danger data-confirm-ok="Löschen" style="margin-top:24px">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-small btn-danger">Bestellung löschen</button>
        </form>
    </aside>
</div>
