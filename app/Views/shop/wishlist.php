<?php $fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c); ?>
<div class="shop">
    <?= \Core\View::fetch('shop/_bar', []) ?>
    <h1 class="cms-heading">Merkliste</h1>

    <?php if (empty($products)): ?>
        <p class="muted">Deine Merkliste ist leer.</p>
        <p><a class="cms-button" href="<?= e(\Core\Shop::url()) ?>">Weiter einkaufen</a></p>
    <?php else: ?>
        <div class="shop-grid">
            <?php foreach ($products as $p): ?>
                <?= \Core\View::fetch('shop/_card', ['p' => $p, 'fmt' => $fmt]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
