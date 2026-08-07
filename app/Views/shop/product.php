<?php
$fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c);
$hasCompare = ($product['compare_price'] ?? null) !== null && (int) $product['compare_price'] > (int) $product['price'];
$soldOut = $product['stock'] !== null && (int) $product['stock'] <= 0;
$lowStock = $product['stock'] !== null && (int) $product['stock'] > 0 && (int) $product['stock'] <= 5;
$loggedIn = \Core\CustomerAuth::check();
$inWishlist = $loggedIn && \Models\ShopWishlist::has((int) \Core\CustomerAuth::id(), (int) $product['id']);
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
            <h1 class="cms-heading">
                <?= e($product['name']) ?>
                <?php if ($loggedIn): ?>
                    <form method="post" action="<?= e(\Core\Shop::url('merkliste/' . ($inWishlist ? 'entfernen' : 'hinzufuegen'))) ?>" class="shop-wish-form shop-wish-form-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
                        <button type="submit" class="shop-wish-btn" aria-label="<?= $inWishlist ? 'Von der Merkliste entfernen' : 'Auf die Merkliste' ?>"><?= $inWishlist ? '♥' : '♡' ?></button>
                    </form>
                <?php endif; ?>
            </h1>
            <?php if (!empty($product['sku'])): ?><p class="muted small">Art.-Nr.: <?= e($product['sku']) ?></p><?php endif; ?>
            <?php if ($reviewSummary['count'] > 0): ?>
                <p class="shop-rating">
                    <span class="shop-stars" aria-hidden="true"><?= str_repeat('★', (int) round($reviewSummary['avg'])) . str_repeat('☆', 5 - (int) round($reviewSummary['avg'])) ?></span>
                    <span class="muted small"><?= e(number_format($reviewSummary['avg'], 1, ',', '.')) ?> von 5 (<?= (int) $reviewSummary['count'] ?> Bewertung<?= $reviewSummary['count'] === 1 ? '' : 'en' ?>)</span>
                </p>
            <?php endif; ?>
            <div class="shop-product-price">
                <?php if ($hasCompare): ?><span class="shop-price-old"><?= e($fmt($product['compare_price'])) ?></span><?php endif; ?>
                <span class="shop-price-big" id="shop-live-price"><?= e($fmt($product['price'])) ?></span>
            </div>
            <?php if (\Core\Shop::taxMode() === 'inclusive'): ?><p class="shop-tax-note muted small">inkl. MwSt., zzgl. Versandkosten</p><?php endif; ?>
            <?php if (!empty($product['short_desc'])): ?><p class="shop-product-short"><?= e($product['short_desc']) ?></p><?php endif; ?>

            <?php if (!empty($tiers)): ?>
                <table class="shop-tiers">
                    <caption>Mengenrabatt</caption>
                    <tr><th>ab 1</th><?php foreach ($tiers as $t): ?><th>ab <?= (int) $t['min'] ?></th><?php endforeach; ?></tr>
                    <tr><td><?= e($fmt($product['price'])) ?></td><?php foreach ($tiers as $t): ?><td><?= e($fmt($t['price'])) ?></td><?php endforeach; ?></tr>
                </table>
            <?php endif; ?>

            <?php if ($soldOut): ?>
                <p class="shop-soldout">Zurzeit ausverkauft</p>
            <?php else: ?>
                <?php if ($lowStock): ?><p class="shop-lowstock muted small">Nur noch <?= (int) $product['stock'] ?> auf Lager</p><?php endif; ?>
                <form method="post" action="<?= e(\Core\Shop::url('warenkorb/add')) ?>" class="shop-buy" id="shop-buy"
                      data-base="<?= (int) $product['price'] ?>"
                      data-tiers='<?= e(json_encode(array_map(fn ($t) => ['min' => $t['min'], 'price' => $t['price']], $tiers))) ?>'
                      data-symbol="<?= e(\Core\Shop::currencySymbol()) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <?php foreach ($optionGroups as $g): ?>
                        <label class="shop-opt">
                            <span><?= e($g['name']) ?></span>
                            <select name="opt[<?= e($g['name']) ?>]" class="shop-opt-select">
                                <?php foreach ($g['choices'] as $c): ?>
                                    <option value="<?= e($c['label']) ?>" data-diff="<?= (int) $c['diff'] ?>">
                                        <?= e($c['label']) ?><?= (int) $c['diff'] !== 0 ? ' (' . ((int) $c['diff'] > 0 ? '+' : '') . $fmt($c['diff']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endforeach; ?>
                    <div class="shop-buy-row">
                        <input type="number" name="qty" value="1" min="1" class="shop-qty">
                        <button type="submit" class="cms-button shop-add">In den Warenkorb</button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if (!empty($product['description'])): ?>
                <div class="shop-product-desc cms-text"><?= $product['description'] ?></div>
            <?php endif; ?>
        </div>
    </div>

    <section class="shop-reviews">
        <h2 class="cms-heading">Bewertungen</h2>
        <?php if (empty($reviews)): ?>
            <p class="muted">Noch keine Bewertungen für dieses Produkt.</p>
        <?php else: ?>
            <ul class="shop-review-list">
                <?php foreach ($reviews as $r): ?>
                    <li class="shop-review">
                        <span class="shop-stars" aria-hidden="true"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></span>
                        <strong><?= e($r['name']) ?></strong>
                        <span class="muted small"><?= e(format_date_de($r['created_at'])) ?></span>
                        <?php if (!empty($r['text'])): ?><p><?= nl2br(e($r['text'])) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($canReview): ?>
            <form method="post" action="<?= e(\Core\Shop::url('produkt/' . $product['slug'] . '/bewertung')) ?>" class="shop-review-form">
                <?= csrf_field() ?>
                <h3>Bewertung abgeben</h3>
                <label class="shop-stars-input">
                    <?php foreach ([5, 4, 3, 2, 1] as $n): ?>
                        <input type="radio" name="rating" value="<?= $n ?>" id="rating-<?= $n ?>" <?= $n === 5 ? 'checked' : '' ?>>
                        <label for="rating-<?= $n ?>"><?= $n ?> ★</label>
                    <?php endforeach; ?>
                </label>
                <textarea name="text" rows="3" placeholder="Deine Erfahrung mit dem Produkt (optional)"></textarea>
                <button type="submit" class="cms-button">Bewertung senden</button>
            </form>
        <?php elseif (\Core\CustomerAuth::check()): ?>
            <p class="muted small">Du kannst dieses Produkt bewerten, sobald du es bestellt hast.</p>
        <?php endif; ?>
    </section>

    <?php foreach ([['accessories', 'Zubehör', $accessories], ['cross', 'Passt dazu', $crossSell]] as [$k, $heading, $list]): ?>
        <?php if (!empty($list)): ?>
            <section class="shop-related">
                <h2 class="cms-heading"><?= e($heading) ?></h2>
                <div class="shop-grid">
                    <?php foreach ($list as $rp): ?>
                        <?= \Core\View::fetch('shop/_card', ['p' => $rp, 'fmt' => $fmt]) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php if (!$soldOut): ?>
<script>
(function () {
    var form = document.getElementById('shop-buy');
    if (!form) return;
    var base = parseInt(form.dataset.base, 10) || 0;
    var symbol = form.dataset.symbol || '€';
    var tiers = [];
    try { tiers = JSON.parse(form.dataset.tiers || '[]'); } catch (e) {}
    var qtyInput = form.querySelector('input[name=qty]');
    var priceEl = document.getElementById('shop-live-price');

    function fmt(c) { return (c / 100).toFixed(2).replace('.', ',') + ' ' + symbol; }
    function unitBase(qty) {
        var price = base;
        tiers.forEach(function (t) { if (qty >= t.min) price = t.price; });
        return price;
    }
    function optDiff() {
        var d = 0;
        form.querySelectorAll('.shop-opt-select').forEach(function (s) {
            d += parseInt(s.options[s.selectedIndex].dataset.diff, 10) || 0;
        });
        return d;
    }
    function update() {
        var qty = Math.max(1, parseInt(qtyInput.value, 10) || 1);
        priceEl.textContent = fmt(unitBase(qty) + optDiff());
    }
    form.addEventListener('change', update);
    form.addEventListener('input', update);
    update();
})();
</script>
<?php endif; ?>
