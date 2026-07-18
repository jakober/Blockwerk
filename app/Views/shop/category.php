<?php $fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c); ?>
<div class="shop">
    <?= \Core\View::fetch('shop/_bar', []) ?>
    <h1 class="cms-heading"><?= e($category['name']) ?></h1>
    <?php if (!empty($category['description'])): ?><p class="shop-cat-desc"><?= e($category['description']) ?></p><?php endif; ?>

    <?php if (!empty($subcategories)): ?>
        <div class="shop-subcats">
            <?php foreach ($subcategories as $c): ?>
                <a class="shop-chip" href="<?= e(\Core\Shop::url('kategorie/' . $c['slug'])) ?>"><?= e($c['name']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="get" class="shop-filters">
        <input type="search" name="q" value="<?= e($opts['search']) ?>" placeholder="Suchen …">
        <input type="text" name="min" value="<?= e($_GET['min'] ?? '') ?>" placeholder="Preis ab" inputmode="decimal" class="shop-filter-price">
        <input type="text" name="max" value="<?= e($_GET['max'] ?? '') ?>" placeholder="Preis bis" inputmode="decimal" class="shop-filter-price">
        <select name="sort">
            <?php foreach (['' => 'Empfohlen', 'price_asc' => 'Preis aufsteigend', 'price_desc' => 'Preis absteigend', 'name' => 'Name (A–Z)', 'newest' => 'Neueste'] as $k => $label): ?>
                <option value="<?= $k ?>" <?= ($opts['sort'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="cms-button">Filtern</button>
    </form>

    <?php if (empty($products)): ?>
        <p class="muted">Keine Produkte gefunden.</p>
    <?php else: ?>
        <div class="shop-grid">
            <?php foreach ($products as $p): ?>
                <?= \Core\View::fetch('shop/_card', ['p' => $p, 'fmt' => $fmt]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
