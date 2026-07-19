<?php $count = \Core\Cart::count(); ?>
<div class="shop-bar">
    <a class="shop-bar-home" href="<?= e(\Core\Shop::url()) ?>">← Shop</a>
    <span class="shop-bar-right">
        <?php if (\Core\CustomerAuth::check()): ?>
            <a class="shop-bar-account" href="<?= e(\Core\Shop::url('konto')) ?>">👤 Mein Konto</a>
        <?php else: ?>
            <a class="shop-bar-account" href="<?= e(\Core\Shop::url('login')) ?>">👤 Anmelden</a>
        <?php endif; ?>
        <a class="shop-bar-cart" href="<?= e(\Core\Shop::url('warenkorb')) ?>">
            🛒 Warenkorb<?php if ($count > 0): ?> <span class="shop-cart-count"><?= (int) $count ?></span><?php endif; ?>
        </a>
    </span>
</div>
<?= \Core\View::fetch('shop/_flash', []) ?>
