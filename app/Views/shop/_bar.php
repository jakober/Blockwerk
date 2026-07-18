<?php $count = \Core\Cart::count(); ?>
<div class="shop-bar">
    <a class="shop-bar-home" href="<?= e(\Core\Shop::url()) ?>">← Shop</a>
    <a class="shop-bar-cart" href="<?= e(\Core\Shop::url('warenkorb')) ?>">
        🛒 Warenkorb<?php if ($count > 0): ?> <span class="shop-cart-count"><?= (int) $count ?></span><?php endif; ?>
    </a>
</div>
<?= \Core\View::fetch('shop/_flash', []) ?>
