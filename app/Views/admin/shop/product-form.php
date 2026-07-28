<?php
$isEdit = $product !== null;
$action = $isEdit ? '/admin/shop/products/' . $product['id'] : '/admin/shop/products';
$priceStr = static fn ($cents) => $cents === null || $cents === '' ? '' : number_format(((int) $cents) / 100, 2, ',', '');
?>
<div class="card">
    <form method="post" action="<?= e(url($action)) ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group grow">
                <label for="name">Produktname</label>
                <input type="text" id="name" name="name" value="<?= e($product['name'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group grow">
                <label for="slug">Slug (leer = automatisch)</label>
                <input type="text" id="slug" name="slug" value="<?= e($product['slug'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group grow">
                <label for="category_id">Kategorie</label>
                <select id="category_id" name="category_id">
                    <option value="0">– keine –</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($product['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= str_repeat('— ', (int) $c['depth']) . e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="sku">Artikelnummer (SKU)</label>
                <input type="text" id="sku" name="sku" value="<?= e($product['sku'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="price">Preis (€)</label>
                <input type="text" id="price" name="price" value="<?= e($priceStr($product['price'] ?? '')) ?>" placeholder="19,90" inputmode="decimal">
            </div>
            <div class="form-group">
                <label for="compare_price">Streichpreis (optional)</label>
                <input type="text" id="compare_price" name="compare_price" value="<?= e($priceStr($product['compare_price'] ?? '')) ?>" placeholder="24,90" inputmode="decimal">
            </div>
            <div class="form-group">
                <label for="stock">Lagerbestand (leer = unbegrenzt)</label>
                <input type="number" id="stock" name="stock" value="<?= e($product['stock'] ?? '') ?>" min="0">
            </div>
            <div class="form-group">
                <label for="weight">Gewicht in kg (optional, für gewichtsabhängigen Versand)</label>
                <input type="number" step="0.01" id="weight" name="weight" value="<?= e(!empty($product['weight']) ? rtrim(rtrim(number_format((int) $product['weight'] / 1000, 3, '.', ''), '0'), '.') : '') ?>" min="0">
            </div>
        </div>

        <div class="form-group">
            <label>Produktbild</label>
            <div class="image-field">
                <input type="text" name="image" id="image" value="<?= e($product['image'] ?? '') ?>" placeholder="Bild-URL oder aus der Mediathek wählen">
                <button type="button" class="btn" data-media-pick="#image">Mediathek</button>
            </div>
        </div>

        <div class="form-group">
            <label for="short_desc">Kurzbeschreibung (für Listen/Kacheln)</label>
            <textarea id="short_desc" name="short_desc" rows="2"><?= e($product['short_desc'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="description">Beschreibung</label>
            <textarea id="description" name="description" data-richtext rows="10"><?= e($product['description'] ?? '') ?></textarea>
        </div>

        <details class="shop-extra"<?= !empty($tiers) ? ' open' : '' ?>>
            <summary>Staffelpreise (Mengenrabatt)</summary>
            <p class="muted small">Ab einer bestimmten Menge gilt ein günstigerer Stückpreis. Leer lassen = keine Staffel.</p>
            <table class="shop-tier-table"><thead><tr><th>ab Menge</th><th>Stückpreis (€)</th><th></th></tr></thead>
                <tbody id="tier-rows">
                    <?php foreach ($tiers as $t): ?>
                        <tr class="tier-row">
                            <td><input type="number" name="tier_min[]" min="2" value="<?= (int) $t['min'] ?>"></td>
                            <td><input type="text" name="tier_price[]" inputmode="decimal" value="<?= e($priceStr($t['price'])) ?>"></td>
                            <td><button type="button" class="btn btn-small btn-ghost tier-del">✕</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn btn-small" id="tier-add">+ Staffel hinzufügen</button>
        </details>

        <details class="shop-extra"<?= !empty($optionGroups) ? ' open' : '' ?>>
            <summary>Eigenschaften / Varianten (z. B. Größe, Farbe)</summary>
            <p class="muted small">Der Kunde wählt beim Kauf eine Ausprägung je Eigenschaft. Ein optionaler Aufpreis (€) wird auf den Preis addiert (auch negativ möglich).</p>
            <input type="hidden" name="options" id="options-json" value="<?= e($product['options'] ?? '') ?>">
            <div id="opt-groups"></div>
            <button type="button" class="btn btn-small" id="opt-add-group">+ Eigenschaft hinzufügen</button>
        </details>

        <?php if (!empty($others)): ?>
            <div class="form-row">
                <div class="form-group grow">
                    <label for="cross_sell">Cross-Selling (wird auf der Produktseite als „Passt dazu" gezeigt)</label>
                    <select id="cross_sell" name="cross_sell[]" multiple size="5">
                        <?php foreach ($others as $o): ?>
                            <option value="<?= (int) $o['id'] ?>" <?= in_array((int) $o['id'], $crossIds, true) ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group grow">
                    <label for="accessories">Zubehör (wird auf der Produktseite als „Zubehör" gezeigt)</label>
                    <select id="accessories" name="accessories[]" multiple size="5">
                        <?php foreach ($others as $o): ?>
                            <option value="<?= (int) $o['id'] ?>" <?= in_array((int) $o['id'], $accIds, true) ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p class="muted small">Mehrfachauswahl mit Strg/⌘ + Klick.</p>
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label for="position">Sortierung</label>
                <input type="number" id="position" name="position" value="<?= e($product['position'] ?? 0) ?>">
            </div>
            <div class="form-group checkbox-group grow" style="align-self:flex-end">
                <label><input type="checkbox" name="active" <?= $isEdit ? ((int) $product['active'] ? 'checked' : '') : 'checked' ?>> Aktiv (im Shop sichtbar)</label>
                <label><input type="checkbox" name="featured" <?= (int) ($product['featured'] ?? 0) ? 'checked' : '' ?>> Empfohlen (auf Shop-Startseite)</label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Speichern' : 'Anlegen' ?></button>
            <a class="btn btn-ghost" href="<?= e(url('/admin/shop/products')) ?>">Abbrechen</a>
        </div>
    </form>
</div>
<script src="<?= e(asset('/assets/js/admin-tools.js')) ?>"></script>
<script>
(function () {
    function euro(cents) { return (Number(cents || 0) / 100).toFixed(2).replace('.', ','); }

    /* ---- Staffelpreise ---- */
    var tierBody = document.getElementById('tier-rows');
    document.getElementById('tier-add').addEventListener('click', function () {
        var tr = document.createElement('tr');
        tr.className = 'tier-row';
        tr.innerHTML = '<td><input type="number" name="tier_min[]" min="2" placeholder="5"></td>' +
            '<td><input type="text" name="tier_price[]" inputmode="decimal" placeholder="17,90"></td>' +
            '<td><button type="button" class="btn btn-small btn-ghost tier-del">✕</button></td>';
        tierBody.appendChild(tr);
    });
    tierBody.addEventListener('click', function (e) {
        if (e.target.classList.contains('tier-del')) e.target.closest('tr').remove();
    });

    /* ---- Eigenschaften / Varianten ---- */
    var hidden = document.getElementById('options-json');
    var wrap = document.getElementById('opt-groups');

    function serialize() {
        var groups = [];
        wrap.querySelectorAll('.opt-group').forEach(function (g) {
            var name = g.querySelector('.opt-name').value.trim();
            var choices = [];
            g.querySelectorAll('.opt-choice').forEach(function (c) {
                var label = c.querySelector('.opt-label').value.trim();
                var diff = c.querySelector('.opt-diff').value.trim() || '0';
                if (label) choices.push({ label: label, diff: diff });
            });
            if (name && choices.length) groups.push({ name: name, choices: choices });
        });
        hidden.value = JSON.stringify(groups);
    }

    function choiceRow(label, diff) {
        var row = document.createElement('div');
        row.className = 'opt-choice';
        row.innerHTML = '<input type="text" class="opt-label" placeholder="z. B. XL" value="">' +
            '<input type="text" class="opt-diff" inputmode="decimal" placeholder="Aufpreis €" value="">' +
            '<button type="button" class="btn btn-small btn-ghost opt-choice-del">✕</button>';
        row.querySelector('.opt-label').value = label || '';
        row.querySelector('.opt-diff').value = diff || '';
        return row;
    }

    function groupBlock(name, choices) {
        var g = document.createElement('div');
        g.className = 'opt-group';
        g.innerHTML = '<div class="opt-group-head"><input type="text" class="opt-name" placeholder="Name der Eigenschaft, z. B. Größe">' +
            '<button type="button" class="btn btn-small btn-ghost opt-group-del">Eigenschaft entfernen</button></div>' +
            '<div class="opt-choices"></div>' +
            '<button type="button" class="btn btn-small opt-add-choice">+ Ausprägung</button>';
        g.querySelector('.opt-name').value = name || '';
        var cc = g.querySelector('.opt-choices');
        (choices && choices.length ? choices : [{}]).forEach(function (c) { cc.appendChild(choiceRow(c.label, c.diff)); });
        wrap.appendChild(g);
    }

    // Bestehende Optionen laden (Aufpreis von Cent -> Euro-Anzeige).
    try {
        var init = JSON.parse(hidden.value || '[]');
        (init || []).forEach(function (g) {
            groupBlock(g.name, (g.choices || []).map(function (c) { return { label: c.label, diff: c.diff ? euro(c.diff) : '' }; }));
        });
    } catch (e) {}
    serialize();

    document.getElementById('opt-add-group').addEventListener('click', function () { groupBlock('', [{}]); serialize(); });
    wrap.addEventListener('click', function (e) {
        if (e.target.classList.contains('opt-group-del')) { e.target.closest('.opt-group').remove(); serialize(); }
        else if (e.target.classList.contains('opt-choice-del')) { e.target.closest('.opt-choice').remove(); serialize(); }
        else if (e.target.classList.contains('opt-add-choice')) { e.target.closest('.opt-group').querySelector('.opt-choices').appendChild(choiceRow('', '')); serialize(); }
    });
    wrap.addEventListener('input', serialize);
    // Sicherheit: vor dem Absenden final serialisieren.
    hidden.form.addEventListener('submit', serialize);
})();
</script>
