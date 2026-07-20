<?php $fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c); ?>
<div class="shop">
    <?= \Core\View::fetch('shop/_bar', []) ?>
    <div class="shop-confirm">
        <div class="shop-confirm-check">✓</div>
        <h1 class="cms-heading">Vielen Dank für deine Bestellung!</h1>
        <p>Deine Bestellnummer lautet <strong><?= e($order['number']) ?></strong>. Eine Bestätigung geht an <strong><?= e($order['email']) ?></strong>.</p>

        <?php if ($order['payment_method'] === 'prepay' && $order['payment_status'] !== 'paid' && trim($bankInfo) !== ''): ?>
            <div class="shop-bank">
                <h3>Bitte überweise den Betrag auf folgendes Konto:</h3>
                <p><?= nl2br(e($bankInfo)) ?></p>
                <p class="muted small">Verwendungszweck: <?= e($order['number']) ?></p>
            </div>
        <?php endif; ?>

        <table class="shop-cart-table">
            <thead><tr><th>Artikel</th><th>Menge</th><th>Summe</th></tr></thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                    <tr><td><?= e($it['name']) ?></td><td><?= (int) $it['qty'] ?></td><td><?= e($fmt((int) $it['price'] * (int) $it['qty'])) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="2" style="text-align:right">Zwischensumme</td><td><?= e($fmt($order['subtotal'])) ?></td></tr>
                <tr><td colspan="2" style="text-align:right">Versand<?= $order['shipping_method'] ? ' (' . e($order['shipping_method']) . ')' : '' ?></td><td><?= e($fmt($order['shipping_cost'])) ?></td></tr>
                <tr><td colspan="2" style="text-align:right"><strong>Gesamt</strong></td><td><strong><?= e($fmt($order['total'])) ?></strong></td></tr>
            </tfoot>
        </table>

        <p class="shop-confirm-meta muted small">Zahlungsart: <?= e($order['payment_method']) ?> · Zahlung: <?= $order['payment_status'] === 'paid' ? 'bezahlt' : 'offen' ?>
            · Status: <span class="shop-status is-<?= e($order['status']) ?>"><?= e(\Models\ShopOrder::statusLabel($order['status'])) ?></span></p>
        <p>
            <a class="cms-button" href="<?= e(\Core\Shop::url()) ?>">Weiter einkaufen</a>
            <a class="cms-button cms-button-ghost" href="<?= e(\Core\Shop::url('bestellung/' . $order['token'] . '/rechnung')) ?>" target="_blank" rel="noopener">🧾 Rechnung</a>
            <?php if (\Core\CustomerAuth::check()): ?>
                <a class="cms-button cms-button-ghost" href="<?= e(\Core\Shop::url('konto')) ?>">Meine Bestellungen</a>
            <?php endif; ?>
        </p>
    </div>
</div>
