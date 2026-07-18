<link rel="stylesheet" href="<?= e(asset('/assets/css/cms-blocks.css')) ?>">
<?= $designHead ?>

<div class="card">
    <h2 class="card-title">Live-Vorschau</h2>
    <p class="muted small">So sieht dein Menü auf der Website aus – fahre mit der Maus über die Menüpunkte, um Aufklapp- und Mega-Menü zu testen. Jede Änderung unten wird sofort übernommen.</p>
    <div class="menu-preview" id="menu-preview"><?= $previewHtml ?></div>
</div>

<form method="post" action="<?= e(url('/admin/menu')) ?>" id="menu-form">
    <?= csrf_field() ?>

    <div class="card">
        <h2 class="card-title">Menü-Vorlage</h2>
        <div class="menu-variants">
            <?php
            $variants = [
                'dropdown' => ['Dropdown', 'Untermenüs klappen beim Überfahren auf – der Klassiker.'],
                'mega' => ['Mega-Menü', 'Große Übersicht: alle Unterpunkte auf einen Blick.'],
                'vertical' => ['Vertikal', 'Untereinander – für Seitenleisten-Layouts.'],
                'simple' => ['Nur oberste Ebene', 'Ohne Untermenüs, maximal reduziert.'],
            ];
            foreach ($variants as $value => [$label, $hint]): ?>
                <label class="menu-variant<?= $design['variant'] === $value ? ' is-active' : '' ?>">
                    <input type="radio" name="variant" value="<?= e($value) ?>" <?= $design['variant'] === $value ? 'checked' : '' ?>>
                    <strong><?= e($label) ?></strong>
                    <span class="muted small"><?= e($hint) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="form-group" id="mega-full-wrap" <?= $design['variant'] === 'mega' ? '' : 'hidden' ?>>
            <label class="check"><input type="checkbox" name="mega_full" value="1" <?= $design['mega_full'] ? 'checked' : '' ?>> Mega-Menü über die volle Seitenbreite aufklappen</label>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">Schrift &amp; Abstände</h2>
        <div class="form-row">
            <div class="form-group grow">
                <label for="font_size">Schriftgröße (px)</label>
                <input type="number" id="font_size" name="font_size" min="10" max="40" value="<?= (int) $design['font_size'] ?>">
            </div>
            <div class="form-group grow">
                <label for="item_padding">Innenabstand der Menüpunkte (px)</label>
                <input type="number" id="item_padding" name="item_padding" min="0" max="60" value="<?= (int) $design['item_padding'] ?>">
            </div>
            <div class="form-group grow">
                <label for="gap">Abstand zwischen Menüpunkten (px)</label>
                <input type="number" id="gap" name="gap" min="0" max="60" value="<?= (int) $design['gap'] ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group grow">
                <label for="align">Ausrichtung</label>
                <select id="align" name="align">
                    <option value="left" <?= $design['align'] === 'left' ? 'selected' : '' ?>>Links</option>
                    <option value="center" <?= $design['align'] === 'center' ? 'selected' : '' ?>>Zentriert</option>
                    <option value="right" <?= $design['align'] === 'right' ? 'selected' : '' ?>>Rechts</option>
                </select>
            </div>
            <div class="form-group grow">
                <label for="transform">Schreibweise</label>
                <select id="transform" name="transform">
                    <option value="normal" <?= $design['transform'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="uppercase" <?= $design['transform'] === 'uppercase' ? 'selected' : '' ?>>GROSSBUCHSTABEN</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">Farben</h2>
        <p class="muted small">Ohne eigene Farbe übernimmt das Menü automatisch die Farben deines Layouts (Gestaltung).</p>
        <div class="form-row">
            <?php
            $colors = [
                'color' => 'Textfarbe der Menüpunkte',
                'dropdown_bg' => 'Hintergrund des Aufklapp-Menüs',
                'dropdown_text' => 'Textfarbe im Aufklapp-Menü',
            ];
            foreach ($colors as $key => $label): $set = $design[$key] !== ''; ?>
                <div class="form-group grow">
                    <label><?= e($label) ?></label>
                    <div class="color-pick">
                        <label class="check"><input type="checkbox" name="<?= e($key) ?>_use" value="1" <?= $set ? 'checked' : '' ?>> Eigene Farbe</label>
                        <input type="color" name="<?= e($key) ?>" value="<?= e($set ? $design[$key] : '#333333') ?>" <?= $set ? '' : 'disabled' ?>>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">Mobil</h2>
        <div class="form-row">
            <div class="form-group grow">
                <label for="breakpoint">Mobiles Burger-Menü ab Bildschirmbreite (px)</label>
                <input type="number" id="breakpoint" name="breakpoint" min="0" max="2000" step="10" value="<?= (int) $design['breakpoint'] ?>">
                <p class="muted small">Unterhalb dieser Breite zeigt die Website automatisch das Burger-Menü. 0 = nie.</p>
            </div>
        </div>
    </div>

    <div class="page-actions">
        <button type="submit" class="btn btn-primary">Menü speichern</button>
        <span class="muted small">Das Menü-Template wird beim Speichern automatisch im Hintergrund erzeugt und in allen Layouts übernommen.</span>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('menu-form');
    var preview = document.getElementById('menu-preview');
    var megaWrap = document.getElementById('mega-full-wrap');
    var timer = null;

    function data() {
        var f = new FormData(form);
        var d = {
            variant: f.get('variant') || 'dropdown',
            align: f.get('align') || 'left',
            font_size: parseInt(f.get('font_size'), 10) || 16,
            item_padding: parseInt(f.get('item_padding'), 10) || 0,
            gap: parseInt(f.get('gap'), 10) || 0,
            transform: f.get('transform') || 'normal',
            mega_full: f.get('mega_full') ? 1 : 0,
            breakpoint: parseInt(f.get('breakpoint'), 10) || 0,
            color: '', dropdown_bg: '', dropdown_text: ''
        };
        ['color', 'dropdown_bg', 'dropdown_text'].forEach(function (key) {
            if (f.get(key + '_use')) { d[key] = f.get(key) || ''; }
        });
        return d;
    }

    function refresh() {
        fetch(window.CMS_BASE + '/admin/preview/blocks', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': form.querySelector('[name=_csrf]').value },
            body: JSON.stringify({ blocks: [{ type: 'l-menu', data: data() }] })
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res && res.html) { preview.innerHTML = res.html[0]; }
        });
    }

    form.addEventListener('input', function () {
        // Radio-Karten optisch aktiv schalten, Mega-Option ein-/ausblenden, Farbfelder (de)aktivieren
        form.querySelectorAll('.menu-variant').forEach(function (el) {
            el.classList.toggle('is-active', el.querySelector('input').checked);
        });
        megaWrap.hidden = form.querySelector('[name=variant]:checked').value !== 'mega';
        ['color', 'dropdown_bg', 'dropdown_text'].forEach(function (key) {
            form.querySelector('[name="' + key + '"]').disabled = !form.querySelector('[name="' + key + '_use"]').checked;
        });
        clearTimeout(timer);
        timer = setTimeout(refresh, 250);
    });
})();
</script>
