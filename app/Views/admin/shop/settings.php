<?php
$priceStr = static fn ($cents) => $cents === null || $cents === '' ? '' : number_format(((int) $cents) / 100, 2, ',', '');
?>
<form method="post" action="<?= e(url('/admin/shop/settings')) ?>">
    <?= csrf_field() ?>
    <div class="card">
        <h2>Shop</h2>
        <p class="muted small">Der Shop ist aktiv. <span class="badge badge-green">Aktiv</span> Ein-/Ausschalten kannst du ihn unter <a href="<?= e(url('/admin/settings#shop')) ?>">Einstellungen</a>. Die gewählte Hauptseite und alles darunter (Kategorien, Produkte, Warenkorb, Kasse) wird vom Shop übernommen.</p>

        <div class="form-row">
            <div class="form-group grow">
                <label for="root_page">Shop-Hauptseite</label>
                <select id="root_page" name="root_page">
                    <option value="0">– bitte wählen –</option>
                    <?php foreach ($pages as $pg): ?>
                        <option value="<?= (int) $pg['id'] ?>" <?= (int) $s['root_page'] === (int) $pg['id'] ? 'selected' : '' ?>>
                            <?= str_repeat('— ', (int) $pg['depth']) . e($pg['title']) ?> (/<?= e($pg['slug']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="muted small">Diese Seite wird zur Shop-Startseite. Produkt- und Kategorieseiten liegen darunter, z. B. <code>/<em>seite</em>/kategorie/…</code>.</p>
            </div>
            <div class="form-group">
                <label for="currency">Währung</label>
                <input type="text" id="currency" name="currency" value="<?= e($s['currency']) ?>" style="max-width:90px">
            </div>
            <div class="form-group">
                <label for="symbol">Symbol</label>
                <input type="text" id="symbol" name="symbol" value="<?= e($s['symbol']) ?>" style="max-width:70px">
            </div>
        </div>
        <div class="form-group">
            <label for="email">Benachrichtigungs-E-Mail für neue Bestellungen (optional)</label>
            <input type="email" id="email" name="email" value="<?= e($s['email']) ?>">
        </div>
    </div>

    <div class="card">
        <h2>Zahlungsarten</h2>
        <div class="form-group checkbox-group">
            <label><input type="checkbox" name="pay_invoice" <?= $s['pay_invoice'] === '1' ? 'checked' : '' ?>> Kauf auf Rechnung</label>
            <label><input type="checkbox" name="pay_prepay" <?= $s['pay_prepay'] === '1' ? 'checked' : '' ?>> Vorkasse (Überweisung)</label>
            <label><input type="checkbox" name="pay_paypal" <?= $s['pay_paypal'] === '1' ? 'checked' : '' ?>> PayPal</label>
        </div>
        <div class="form-group">
            <label for="bank_info">Bankverbindung (wird bei Vorkasse angezeigt)</label>
            <textarea id="bank_info" name="bank_info" rows="3" placeholder="Kontoinhaber, IBAN, BIC …"><?= e($s['bank_info']) ?></textarea>
        </div>

        <h3>PayPal</h3>
        <p class="muted small">Zugangsdaten aus dem <a href="https://developer.paypal.com/" target="_blank" rel="noopener">PayPal-Entwicklerportal</a> (REST-App: Client-ID &amp; Secret).</p>
        <div class="form-group checkbox-group">
            <label><input type="checkbox" name="paypal_sandbox" <?= $s['paypal_sandbox'] === '1' ? 'checked' : '' ?>> Testmodus (Sandbox)</label>
        </div>
        <div class="form-row">
            <div class="form-group grow">
                <label for="paypal_client_id">PayPal Client-ID</label>
                <input type="text" id="paypal_client_id" name="paypal_client_id" value="<?= e($s['paypal_client_id']) ?>" autocomplete="off">
            </div>
            <div class="form-group grow">
                <label for="paypal_secret">PayPal Secret <?= $s['paypal_secret'] !== '' ? '<span class="badge badge-green">hinterlegt</span>' : '' ?></label>
                <input type="password" id="paypal_secret" name="paypal_secret" value="<?= $s['paypal_secret'] !== '' ? '••••••••••' : '' ?>" autocomplete="off">
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Rechnungsdaten</h2>
        <p class="muted small">Diese Angaben erscheinen als Absender auf den generierten Rechnungen. Ohne Logo wird das Logo der Website verwendet.</p>
        <div class="form-row">
            <div class="form-group grow">
                <label for="inv_company">Firma / Name</label>
                <input type="text" id="inv_company" name="inv_company" value="<?= e($s['inv_company']) ?>" placeholder="Muster GmbH">
            </div>
            <div class="form-group grow">
                <label for="inv_tax">USt-IdNr. / Steuernummer</label>
                <input type="text" id="inv_tax" name="inv_tax" value="<?= e($s['inv_tax']) ?>" placeholder="DE123456789">
            </div>
        </div>
        <div class="form-group">
            <label for="inv_address">Anschrift</label>
            <textarea id="inv_address" name="inv_address" rows="3" placeholder="Straße 1&#10;12345 Stadt&#10;Deutschland"><?= e($s['inv_address']) ?></textarea>
        </div>
        <div class="form-row">
            <div class="form-group grow">
                <label for="inv_email">E-Mail</label>
                <input type="text" id="inv_email" name="inv_email" value="<?= e($s['inv_email']) ?>" placeholder="info@meinshop.de">
            </div>
            <div class="form-group grow">
                <label for="inv_phone">Telefon</label>
                <input type="text" id="inv_phone" name="inv_phone" value="<?= e($s['inv_phone']) ?>">
            </div>
            <div class="form-group grow">
                <label for="inv_website">Website</label>
                <input type="text" id="inv_website" name="inv_website" value="<?= e($s['inv_website']) ?>" placeholder="www.meinshop.de">
            </div>
        </div>
        <div class="form-group">
            <label for="inv_bank">Bankverbindung (auf der Rechnung)</label>
            <textarea id="inv_bank" name="inv_bank" rows="2" placeholder="Bank · IBAN · BIC"><?= e($s['inv_bank']) ?></textarea>
        </div>
        <div class="form-group">
            <label for="inv_logo">Logo (Rechnung)</label>
            <div class="image-field">
                <input type="text" id="inv_logo" name="inv_logo" value="<?= e($s['inv_logo']) ?>" placeholder="Bild-URL oder aus der Mediathek wählen – leer = Website-Logo">
                <button type="button" class="btn" data-media-pick="#inv_logo">Mediathek</button>
            </div>
        </div>
        <div class="form-group">
            <label for="inv_note">Hinweis / Fußzeile (z. B. Umsatzsteuer-Hinweis)</label>
            <textarea id="inv_note" name="inv_note" rows="2" placeholder="z. B. Gemäß §19 UStG wird keine Umsatzsteuer berechnet."><?= e($s['inv_note']) ?></textarea>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Einstellungen speichern</button>
    </div>
</form>

<div class="card">
    <h2>Versandarten</h2>
    <?php
    $tiersToText = static function (array $sh): string {
        $parts = [];
        foreach (\Models\ShopShipping::weightTiers($sh) as $t) {
            $kg = rtrim(rtrim(number_format($t['max'] / 1000, 3, '.', ''), '0'), '.');
            $eur = rtrim(rtrim(number_format($t['price'] / 100, 2, ',', ''), '0'), ',');
            $parts[] = $kg . ':' . $eur;
        }
        return implode('; ', $parts);
    };
    $countryOptions = static function (array $selected): string {
        $sel = array_map('mb_strtolower', $selected);
        $html = '';
        foreach (\Core\Countries::all() as $c) {
            $html .= '<option value="' . e($c) . '"' . (in_array(mb_strtolower($c), $sel, true) ? ' selected' : '') . '>' . e($c) . '</option>';
        }
        return $html;
    };
    ?>
    <p class="muted small">Gewichtsstaffeln als „kg:€" je Stufe, mit <strong>Semikolon</strong> getrennt – z. B. <code>5:20; 20:50</code> = bis 5 kg 20 €, bis 20 kg 50 €. Ohne Staffeln gilt der Pauschalpreis. Das Warenkorbgewicht ergibt sich aus dem Gewicht der Produkte (kein Gewicht = niedrigste Stufe). Länder per Suchfeld auswählen (mehrere möglich; leer = alle Länder); an der Kasse werden nur passende Versandarten angezeigt.</p>
    <?php if (!empty($shipping)): ?>
        <table class="table table-plain">
            <thead><tr><th>Name</th><th>Preis</th><th>Gratis ab</th><th>Länder</th><th>Staffeln (kg:€)</th><th>Aktiv</th><th class="actions-col"></th></tr></thead>
            <tbody>
                <?php foreach ($shipping as $sh): ?>
                    <?php $shipFormId = 'ship-form-' . (int) $sh['id']; ?>
                    <tr>
                        <td><form method="post" action="<?= e(url('/admin/shop/shipping/' . $sh['id'])) ?>" id="<?= e($shipFormId) ?>" class="inline shipping-row"><?= csrf_field() ?>
                            <input type="text" name="name" form="<?= e($shipFormId) ?>" value="<?= e($sh['name']) ?>" required>
                            <input type="text" name="description" form="<?= e($shipFormId) ?>" value="<?= e($sh['description'] ?? '') ?>" placeholder="Beschreibung">
                        </td>
                        <td><input type="text" name="price" form="<?= e($shipFormId) ?>" value="<?= e($priceStr($sh['price'])) ?>" style="max-width:90px" inputmode="decimal"></td>
                        <td><input type="text" name="free_from" form="<?= e($shipFormId) ?>" value="<?= e($priceStr($sh['free_from'] ?? '')) ?>" placeholder="—" style="max-width:90px" inputmode="decimal"></td>
                        <td style="min-width:200px"><select name="countries[]" form="<?= e($shipFormId) ?>" multiple data-country-select data-placeholder="alle Länder"><?= $countryOptions(\Models\ShopShipping::countries($sh)) ?></select></td>
                        <td><input type="text" name="weight_tiers" form="<?= e($shipFormId) ?>" value="<?= e($tiersToText($sh)) ?>" placeholder="z. B. 5:20; 20:50" style="max-width:150px"></td>
                        <td><input type="checkbox" name="active" form="<?= e($shipFormId) ?>" <?= (int) $sh['active'] ? 'checked' : '' ?>></td>
                        <td class="actions-col">
                            <button type="submit" form="<?= e($shipFormId) ?>" class="btn btn-small btn-primary">Speichern</button>
                            </form>
                            <form method="post" action="<?= e(url('/admin/shop/shipping/' . $sh['id'] . '/delete')) ?>" class="inline" data-confirm="Versandart löschen?" data-confirm-danger data-confirm-ok="Löschen"><?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger">✕</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted small">Noch keine Versandarten. Ohne Versandart ist die Kasse trotzdem nutzbar (Versand = 0).</p>
    <?php endif; ?>

    <h3>Versandart hinzufügen</h3>
    <form method="post" action="<?= e(url('/admin/shop/shipping')) ?>" class="shipping-add">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group grow"><label>Name</label><input type="text" name="name" placeholder="z. B. Standardversand" required></div>
            <div class="form-group"><label>Preis (€)</label><input type="text" name="price" placeholder="4,90" inputmode="decimal"></div>
            <div class="form-group"><label>Gratis ab (€, optional)</label><input type="text" name="free_from" placeholder="50,00" inputmode="decimal"></div>
        </div>
        <div class="form-row">
            <div class="form-group grow"><label>Länder (leer = alle)</label><select name="countries[]" multiple data-country-select data-placeholder="Land hinzufügen …"><?= $countryOptions([]) ?></select></div>
            <div class="form-group grow"><label>Gewichtsstaffeln (kg:€)</label><input type="text" name="weight_tiers" placeholder="5:20; 20:50"></div>
        </div>
        <div class="form-group checkbox-group"><label><input type="checkbox" name="active" checked> Aktiv</label></div>
        <button type="submit" class="btn">+ Versandart hinzufügen</button>
    </form>
</div>

<script src="<?= e(asset('/assets/js/country-select.js')) ?>" defer></script>
<!-- Mediathek-Auswahl (data-media-pick) für das Rechnungs-Logo. -->
<script src="<?= e(asset('/assets/js/admin-tools.js')) ?>"></script>
