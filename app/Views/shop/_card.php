<?php
/** @var array $p  Produkt  @var callable $fmt */
$hasCompare = ($p['compare_price'] ?? null) !== null && (int) $p['compare_price'] > (int) $p['price'];
?>
<div class="shop-card">
    <a class="shop-card-link" href="<?= e(\Core\Shop::url('produkt/' . $p['slug'])) ?>">
        <div class="shop-card-img">
            <?php if (!empty($p['image'])): ?>
                <img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
            <?php else: ?><span class="shop-card-noimg">🛍</span><?php endif; ?>
            <?php if ($hasCompare): ?><span class="shop-badge-sale">Angebot</span><?php endif; ?>
        </div>
        <div class="shop-card-name"><?= e($p['name']) ?></div>
    </a>
    <div class="shop-card-price">
        <?php if ($hasCompare): ?><span class="shop-price-old"><?= e($fmt($p['compare_price'])) ?></span><?php endif; ?>
        <span class="shop-price"><?= e($fmt($p['price'])) ?></span>
    </div>
    <form method="post" action="<?= e(\Core\Shop::url('warenkorb/add')) ?>" class="shop-card-form">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
        <?php if ($p['stock'] !== null && (int) $p['stock'] <= 0): ?>
            <button type="button" class="cms-button shop-add" disabled>Ausverkauft</button>
        <?php else: ?>
            <button type="submit" class="cms-button shop-add">In den Warenkorb</button>
        <?php endif; ?>
    </form>
</div>
