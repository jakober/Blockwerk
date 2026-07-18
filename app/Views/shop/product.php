<?php
$fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c);
$hasCompare = ($product['compare_price'] ?? null) !== null && (int) $product['compare_price'] > (int) $product['price'];
$soldOut = $product['stock'] !== null && (int) $product['stock'] <= 0;
?>
<div class="shop">
    <?= \Core\View::fetch('shop/_bar', []) ?>
    <?php if ($category): ?>
        <p class="shop-breadcrumb"><a href="<?= e(\Core\Shop::url('kategorie/' . $category['slug'])) ?>"><?= e($category['name']) ?></a></p>
    <?php endif; ?>

    <div class="shop-product">
        <div class="shop-product-media">
            <?php if (!empty($product['image'])): ?>
                <img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" class="shop-product-img">
            <?php else: ?><div class="shop-product-noimg">🛍</div><?php endif; ?>
            <?php if (!empty($gallery)): ?>
                <div class="shop-gallery">
                    <?php foreach ($gallery as $g): ?><img src="<?= e($g) ?>" alt="" loading="lazy"><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="shop-product-info">
            <h1 class="cms-heading"><?= e($product['name']) ?></h1>
            <?php if (!empty($product['sku'])): ?><p class="muted small">Art.-Nr.: <?= e($product['sku']) ?></p><?php endif; ?>
            <div class="shop-product-price">
                <?php if ($hasCompare): ?><span class="shop-price-old"><?= e($fmt($product['compare_price'])) ?></span><?php endif; ?>
                <span class="shop-price-big"><?= e($fmt($product['price'])) ?></span>
            </div>
            <?php if (!empty($product['short_desc'])): ?><p class="shop-product-short"><?= e($product['short_desc']) ?></p><?php endif; ?>

            <?php if ($soldOut): ?>
                <p class="shop-soldout">Zurzeit ausverkauft</p>
            <?php else: ?>
                <form method="post" action="<?= e(\Core\Shop::url('warenkorb/add')) ?>" class="shop-buy">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <input type="number" name="qty" value="1" min="1" class="shop-qty">
                    <button type="submit" class="cms-button shop-add">In den Warenkorb</button>
                </form>
            <?php endif; ?>

            <?php if (!empty($product['description'])): ?>
                <div class="shop-product-desc cms-text"><?= $product['description'] ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
