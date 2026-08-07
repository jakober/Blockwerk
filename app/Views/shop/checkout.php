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
              data-subtotal="<?= (int) $subtotal ?>" data-discount="<?= (int) $discount ?>" data-symbol="<?= e(\Core\Shop::currencySymbol()) ?>">
            <?= csrf_field() ?>

            <fieldset class="shop-fieldset">
                <legend>Rechnungs- &amp; Lieferadresse</legend>
                <?php if (!empty($addresses)): ?>
                    <label>Gespeicherte Adresse verwenden
                        <select id="saved-address">
                            <option value="">– Adresse wählen –</option>
                            <?php foreach ($addresses as $a): ?>
                                <option value="<?= (int) $a['id'] ?>"
                                    data-address='<?= e(json_encode([
                                        'first_name' => $a['first_name'], 'last_name' => $a['last_name'],
                                        'company' => $a['company'], 'street' => $a['street'],
                                        'zip' => $a['zip'], 'city' => $a['city'],
                                        'country' => $a['country'], 'phone' => $a['phone'],
                                    ], JSON_UNESCAPED_UNICODE)) ?>'>
                                    <?= e($a['label'] !== '' && $a['label'] !== null ? $a['label'] : trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '') . ', ' . ($a['street'] ?? ''))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
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

            <?php if (!empty($customer)): ?>
                <p class="shop-account-note muted small">✓ Angemeldet als <strong><?= e($customer['email']) ?></strong> – die Bestellung wird deinem Konto zugeordnet.</p>
            <?php else: ?>
                <fieldset class="shop-fieldset">
                    <legend>Kundenkonto (optional)</legend>
                    <label class="shop-option">
                        <input type="checkbox" name="create_account" id="create-account" value="1">
                        <span>Kundenkonto anlegen, um meine Bestellungen später einzusehen</span>
                    </label>
                    <label id="account-pw-wrap" hidden>Passwort (mindestens 6 Zeichen)<input type="password" name="account_password" minlength="6" autocomplete="new-password"></label>
                </fieldset>
            <?php endif; ?>

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

            <fieldset class="shop-fieldset">
                <label class="shop-option">
                    <input type="checkbox" name="accept_terms" id="accept-terms" value="1" required>
                    <span>Ich habe die <a href="<?= e($agbUrl) ?>" target="_blank" rel="noopener">AGB</a> und die
                        <a href="<?= e($widerrufUrl) ?>" target="_blank" rel="noopener">Widerrufsbelehrung</a> gelesen und akzeptiere sie.*</span>
                </label>
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
            <?php if (!empty($coupon)): ?>
                <div class="shop-summary-row"><span>Rabatt (<?= e($coupon['code']) ?>)</span><span>−<?= e($fmt($discount)) ?></span></div>
            <?php endif; ?>
            <div class="shop-summary-row"><span>Versand</span><span id="sum-shipping">–</span></div>
            <div class="shop-summary-row shop-summary-total"><span>Gesamt</span><span id="sum-total"><?= e($fmt($subtotal - $discount)) ?></span></div>
            <?php if (\Core\Shop::taxMode() === 'inclusive'): ?><p class="shop-tax-note muted small">inkl. MwSt.</p><?php endif; ?>

            <?php if (!empty($coupon)): ?>
                <form method="post" action="<?= e(\Core\Shop::url('gutschein/entfernen')) ?>" class="inline shop-coupon">
                    <?= csrf_field() ?>
                    <input type="hidden" name="back" value="kasse">
                    <button type="submit" class="shop-remove-link">Gutschein entfernen</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= e(\Core\Shop::url('gutschein')) ?>" class="shop-coupon shop-coupon-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="back" value="kasse">
                    <input type="text" name="coupon_code" placeholder="Gutscheincode">
                    <button type="submit" class="cms-button cms-button-ghost">Einlösen</button>
                </form>
            <?php endif; ?>
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
    var discount = parseInt(form.dataset.discount, 10) || 0;
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
        document.getElementById('sum-total').textContent = fmt(subtotal - discount + ship);
    }
    form.querySelectorAll('input[name=shipping_id]').forEach(function (el) { el.addEventListener('change', updateTotals); });

    // Gespeicherte Adresse übernehmen: füllt die Felder, ohne die Seite neu zu laden.
    var savedAddress = document.getElementById('saved-address');
    if (savedAddress) {
        savedAddress.addEventListener('change', function () {
            var opt = savedAddress.options[savedAddress.selectedIndex];
            var data = opt.getAttribute('data-address');
            if (!data) return;
            try { data = JSON.parse(data); } catch (e) { return; }
            Object.keys(data).forEach(function (key) {
                var field = form.querySelector('[name=' + key + ']');
                if (field) { field.value = data[key] || ''; field.dispatchEvent(new Event('change')); }
            });
        });
    }

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

    // Passwortfeld nur zeigen, wenn „Kundenkonto anlegen" gewählt ist.
    var acc = document.getElementById('create-account');
    var pwWrap = document.getElementById('account-pw-wrap');
    if (acc && pwWrap) {
        var pw = pwWrap.querySelector('input');
        function toggleAcc() { pwWrap.hidden = !acc.checked; if (acc.checked) { pw.required = true; } else { pw.required = false; pw.value = ''; } }
        acc.addEventListener('change', toggleAcc);
        toggleAcc();
    }
})();
</script>
