<?php $fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c); ?>
<div class="shop">
    <?= \Core\View::fetch('shop/_bar', []) ?>
    <h1 class="cms-heading">Shop</h1>

    <?php if (!empty($categories)): ?>
        <h2 class="cms-heading">Kategorien</h2>
        <div class="shop-cats">
            <?php foreach ($categories as $c): ?>
                <a class="shop-cat" href="<?= e(\Core\Shop::url('kategorie/' . $c['slug'])) ?>">
                    <?php if (!empty($c['image'])): ?><img src="<?= e($c['image']) ?>" alt=""><?php endif; ?>
                    <span><?= e($c['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($featured)): ?>
        <h2 class="cms-heading">Empfohlene Produkte</h2>
        <div class="shop-grid">
            <?php foreach ($featured as $p): ?>
                <?= \Core\View::fetch('shop/_card', ['p' => $p, 'fmt' => $fmt]) ?>
            <?php endforeach; ?>
        </div>
    <?php elseif (empty($categories)): ?>
        <p class="muted">Der Shop wird gerade eingerichtet – bald gibt es hier Produkte.</p>
    <?php endif; ?>
</div>
