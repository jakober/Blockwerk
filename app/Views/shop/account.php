<?php
$fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c);
$name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
?>
<div class="shop">
    <?= \Core\View::fetch('shop/_bar', []) ?>
    <div class="shop-account-head">
        <h1 class="cms-heading">Mein Konto</h1>
        <form method="post" action="<?= e(\Core\Shop::url('logout')) ?>" class="inline">
            <?= csrf_field() ?>
            <button type="submit" class="cms-button cms-button-ghost">Abmelden</button>
        </form>
    </div>
    <p class="muted">Angemeldet als <strong><?= e($name !== '' ? $name : $customer['email']) ?></strong> (<?= e($customer['email']) ?>)</p>

    <h2 class="cms-heading">Deine Bestellungen</h2>
    <?php if (empty($orders)): ?>
        <p class="muted">Du hast noch keine Bestellungen.</p>
    <?php else: ?>
        <table class="shop-cart-table shop-orders-table">
            <thead><tr><th>Bestellung</th><th>Datum</th><th>Summe</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><strong><?= e($o['number']) ?></strong></td>
                        <td class="muted small"><?= e(format_date_de($o['created_at'], true)) ?></td>
                        <td><?= e($fmt($o['total'])) ?></td>
                        <td><span class="shop-status is-<?= e($o['status']) ?>"><?= e(\Models\ShopOrder::statusLabel($o['status'])) ?></span></td>
                        <td><a href="<?= e(\Core\Shop::url('bestellung/' . $o['token'])) ?>">Details →</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p style="margin-top:18px"><a class="cms-button" href="<?= e(\Core\Shop::url()) ?>">Weiter einkaufen</a></p>
</div>
