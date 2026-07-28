<?php $fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c); ?>
<div class="shop">
    <?= \Core\View::fetch('shop/_bar', []) ?>
    <h1 class="cms-heading">Warenkorb</h1>

    <?php if (empty($items)): ?>
        <p class="muted">Dein Warenkorb ist leer.</p>
        <p><a class="cms-button" href="<?= e(\Core\Shop::url()) ?>">Weiter einkaufen</a></p>
    <?php else: ?>
        <form method="post" action="<?= e(\Core\Shop::url('warenkorb/update')) ?>">
            <?= csrf_field() ?>
            <table class="shop-cart-table">
                <thead><tr><th>Artikel</th><th>Einzelpreis</th><th>Menge</th><th>Summe</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $it): $p = $it['product']; ?>
                        <tr>
                            <td class="shop-cart-prod">
                                <?php if (!empty($p['image'])): ?><img src="<?= e($p['image']) ?>" alt=""><?php endif; ?>
                                <span>
                                    <a href="<?= e(\Core\Shop::url('produkt/' . $p['slug'])) ?>"><?= e($p['name']) ?></a>
                                    <?php if ($it['optionLabel'] !== ''): ?><br><span class="muted small"><?= e($it['optionLabel']) ?></span><?php endif; ?>
                                </span>
                            </td>
                            <td><?= e($fmt($it['unit'])) ?></td>
                            <td>
                                <input type="hidden" name="ckey[]" value="<?= e($it['key']) ?>">
                                <input type="number" name="qty[]" value="<?= (int) $it['qty'] ?>" min="0" class="shop-qty">
                            </td>
                            <td><?= e($fmt($it['line'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="3" style="text-align:right"><strong>Zwischensumme</strong></td><td><strong><?= e($fmt($subtotal)) ?></strong></td></tr>
                </tfoot>
            </table>
            <p class="muted small">Versandkosten werden im nächsten Schritt berechnet.</p>
            <div class="shop-cart-actions">
                <button type="submit" class="cms-button cms-button-ghost">Warenkorb aktualisieren</button>
                <a class="cms-button" href="<?= e(\Core\Shop::url('kasse')) ?>">Zur Kasse →</a>
            </div>
        </form>

        <div class="shop-cart-removes">
            <?php foreach ($items as $it): $p = $it['product']; ?>
                <form method="post" action="<?= e(\Core\Shop::url('warenkorb/remove')) ?>" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_key" value="<?= e($it['key']) ?>">
                    <button type="submit" class="shop-remove-link">✕ <?= e($p['name']) ?><?= $it['optionLabel'] !== '' ? ' (' . e($it['optionLabel']) . ')' : '' ?> entfernen</button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
