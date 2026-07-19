<?php
$fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c);
$f = $form;
$val = static fn ($k) => e($f[$k] ?? '');
$paypalOn = isset($payments['paypal']);
$clientId = \Core\Shop::paypalClientId();
?>
<div class="shop">
    <?= \Core\View::fetch('shop/_bar', []) ?>
    <h1 class="cms-heading">Kasse</h1>

    <div class="shop-checkout">
        <form method="post" action="<?= e(\Core\Shop::url('kasse')) ?>" id="checkout-form" class="shop-checkout-form"
              data-subtotal="<?= (int) $subtotal ?>" data-symbol="<?= e(\Core\Shop::currencySymbol()) ?>">
            <?= csrf_field() ?>

            <fieldset class="shop-fieldset">
                <legend>Rechnungs- &amp; Lieferadresse</legend>
                <div class="shop-form-row">
                    <label>Vorname*<input type="text" name="first_name" value="<?= $val('first_name') ?>" required></label>
                    <label>Nachname*<input type="text" name="last_name" value="<?= $val('last_name') ?>" required></label>
                </div>
                <label>Firma (optional)<input type="text" name="company" value="<?= $val('company') ?>"></label>
                <label>Straße &amp; Hausnummer*<input type="text" name="street" value="<?= $val('street') ?>" required></label>
                <div class="shop-form-row">
                    <label>PLZ*<input type="text" name="zip" value="<?= $val('zip') ?>" required></label>
                    <label>Ort*<input type="text" name="city" value="<?= $val('city') ?>" required></label>
                </div>
                <?php if (!empty($shipCountries)):
                    $curCountry = (string) ($f['country'] ?? '');
                    $preselect = $curCountry !== ''
                        ? $curCountry
                        : (in_array('Deutschland', $shipCountries, true) ? 'Deutschland' : ($shipCountries[0] ?? ''));
                ?>
                    <label>Land*
                        <select name="country" id="ship-country" required data-country-select data-placeholder="Land wählen …">
                            <?php foreach ($shipCountries as $c): ?>
                                <option value="<?= e($c) ?>" <?= mb_strtolower($c) === mb_strtolower($preselect) ? 'selected' : '' ?>><?= e($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php else: ?>
                    <label>Land<input type="text" name="country" value="<?= $f['country'] ?? '' ? $val('country') : 'Deutschland' ?>"></label>
                <?php endif; ?>
                <div class="shop-form-row">
                    <label>E-Mail*<input type="email" name="email" value="<?= $val('email') ?>" required></label>
                    <label>Telefon (optional)<input type="text" name="phone" value="<?= $val('phone') ?>"></label>
                </div>
                <label>Anmerkung (optional)<textarea name="note" rows="2"><?= $val('note') ?></textarea></label>
            </fieldset>

            <?php if (!empty($shipping)): ?>
                <fieldset class="shop-fieldset">
                    <legend>Versandart</legend>
                    <?php foreach ($shipping as $i => $m): ?>
                        <?php
                        $mCost = \Models\ShopShipping::basePrice($m, (int) ($weight ?? 0));
                        $mCountries = array_map('mb_strtolower', \Models\ShopShipping::countries($m));
                        ?>
                        <label class="shop-option" data-ship-countries='<?= e(json_encode($mCountries)) ?>'>
                            <input type="radio" name="shipping_id" value="<?= (int) $m['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>
                                   data-price="<?= (int) $mCost ?>" data-free="<?= (int) ($m['free_from'] ?? 0) ?>">
                            <span><strong><?= e($m['name']) ?></strong><?php if (!empty($m['description'])): ?> – <?= e($m['description']) ?><?php endif; ?>
                                <?php if (($m['free_from'] ?? null) !== null): ?><em class="muted small">(gratis ab <?= e($fmt($m['free_from'])) ?>)</em><?php endif; ?>
                            </span>
                            <span class="shop-option-price"><?= e($fmt($mCost)) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <p class="muted small" id="ship-none" hidden>In das gewählte Land ist derzeit kein Versand möglich.</p>
                </fieldset>
            <?php endif; ?>

            <fieldset class="shop-fieldset">
                <legend>Zahlungsart</legend>
                <?php $first = true; foreach ($payments as $key => $label): ?>
                    <label class="shop-option">
                        <input type="radio" name="payment_method" value="<?= e($key) ?>" <?= $first ? 'checked' : '' ?> class="shop-pay-radio">
                        <span><?= e($label) ?></span>
                    </label>
                <?php $first = false; endforeach; ?>
                <?php if (empty($payments)): ?><p class="muted">Es ist keine Zahlungsart konfiguriert.</p><?php endif; ?>
            </fieldset>

            <div class="shop-checkout-submit">
                <button type="submit" class="cms-button shop-place-order" id="place-order">Kostenpflichtig bestellen</button>
                <div id="paypal-buttons" hidden></div>
            </div>
        </form>

        <aside class="shop-summary">
            <h2 class="cms-heading">Bestellübersicht</h2>
            <ul class="shop-summary-items">
                <?php foreach ($items as $it): ?>
                    <li><span><?= (int) $it['qty'] ?>× <?= e($it['product']['name']) ?></span><span><?= e($fmt($it['line'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <div class="shop-summary-row"><span>Zwischensumme</span><span id="sum-subtotal"><?= e($fmt($subtotal)) ?></span></div>
            <div class="shop-summary-row"><span>Versand</span><span id="sum-shipping">–</span></div>
            <div class="shop-summary-row shop-summary-total"><span>Gesamt</span><span id="sum-total"><?= e($fmt($subtotal)) ?></span></div>
        </aside>
    </div>
</div>

<?php if ($paypalOn && $clientId !== ''): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= e(rawurlencode($clientId)) ?>&currency=<?= e(rawurlencode(\Core\Shop::currency())) ?>&intent=capture"></script>
<?php endif; ?>
<script src="<?= e(asset('/assets/js/country-select.js')) ?>"></script>
<script>
(function () {
    var form = document.getElementById('checkout-form');
    if (!form) return;
    var subtotal = parseInt(form.dataset.subtotal, 10) || 0;
    var symbol = form.dataset.symbol || '€';
    var csrf = form.querySelector('input[name=_csrf]').value;
    var base = <?= json_encode(\Core\Shop::url()) ?>;

    function fmt(cents) { return (cents / 100).toFixed(2).replace('.', ',') + ' ' + symbol; }
    function shippingCost() {
        var r = form.querySelector('input[name=shipping_id]:checked');
        if (!r) return 0;
        var price = parseInt(r.dataset.price, 10) || 0;
        var free = parseInt(r.dataset.free, 10) || 0;
        return (free > 0 && subtotal >= free) ? 0 : price;
    }
    function updateTotals() {
        var ship = shippingCost();
        document.getElementById('sum-shipping').textContent = ship === 0 ? 'kostenlos' : fmt(ship);
        document.getElementById('sum-total').textContent = fmt(subtotal + ship);
    }
    form.querySelectorAll('input[name=shipping_id]').forEach(function (el) { el.addEventListener('change', updateTotals); });

    // Versandarten nach gewähltem Land ein-/ausblenden.
    var countrySel = document.getElementById('ship-country');
    function filterByCountry() {
        if (!countrySel) return;
        var country = (countrySel.value || '').trim().toLowerCase();
        var anyVisible = false, checkedVisible = false;
        form.querySelectorAll('.shop-option[data-ship-countries]').forEach(function (opt) {
            var list = [];
            try { list = JSON.parse(opt.getAttribute('data-ship-countries') || '[]'); } catch (e) {}
            var serves = list.length === 0 || list.indexOf(country) !== -1;
            opt.style.display = serves ? '' : 'none';
            var radio = opt.querySelector('input[name=shipping_id]');
            if (radio) {
                radio.disabled = !serves;
                if (serves) { anyVisible = true; if (radio.checked) checkedVisible = true; }
                else if (radio.checked) radio.checked = false;
            }
        });
        if (!checkedVisible && anyVisible) {
            var first = form.querySelector('.shop-option[data-ship-countries] input[name=shipping_id]:not([disabled])');
            if (first) first.checked = true;
        }
        var none = document.getElementById('ship-none');
        if (none) none.hidden = anyVisible;
    }
    if (countrySel) countrySel.addEventListener('change', function () { filterByCountry(); updateTotals(); });
    filterByCountry();
    updateTotals();

    // PayPal vs. normale Bestellung je nach Zahlungsart umschalten
    var placeBtn = document.getElementById('place-order');
    var ppWrap = document.getElementById('paypal-buttons');
    var ppRendered = false;

    function currentPayment() {
        var r = form.querySelector('input[name=payment_method]:checked');
        return r ? r.value : '';
    }
    function renderPayPal() {
        if (ppRendered || typeof paypal === 'undefined') return;
        ppRendered = true;
        paypal.Buttons({
            createOrder: function () {
                if (!form.reportValidity()) return Promise.reject(new Error('Bitte Formular ausfüllen'));
                var body = new URLSearchParams(new FormData(form));
                return fetch(base + '/paypal/create', {
                    method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: body
                }).then(function (r) { return r.json(); }).then(function (d) {
                    if (d.error) { window.AdminDialog ? AdminDialog.alert(d.error) : alert(d.error); throw new Error(d.error); }
                    return d.id;
                });
            },
            onApprove: function (data) {
                return fetch(base + '/paypal/capture', {
                    method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'orderID=' + encodeURIComponent(data.orderID)
                }).then(function (r) { return r.json(); }).then(function (d) {
                    if (d.redirect) { window.location = d.redirect; }
                    else { alert(d.error || 'Zahlung fehlgeschlagen'); }
                });
            }
        }).render('#paypal-buttons');
    }
    function toggleMode() {
        var isPP = currentPayment() === 'paypal';
        ppWrap.hidden = !isPP;
        placeBtn.hidden = isPP;
        if (isPP) renderPayPal();
    }
    form.querySelectorAll('input[name=payment_method]').forEach(function (el) { el.addEventListener('change', toggleMode); });
    toggleMode();
})();
</script>
