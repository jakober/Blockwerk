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
                <span class="shop-price-big" id="shop-live-price"><?= e($fmt($product['price'])) ?></span>
            </div>
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
