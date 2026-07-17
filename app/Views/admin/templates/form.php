<?php
$isEdit = $template !== null;
$action = $isEdit ? '/admin/templates/' . $template['id'] : '/admin/templates';
?>
<div class="editor-grid">
    <div class="card">
        <form method="post" action="<?= e(url($action)) ?>">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group grow">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="<?= e($template['name'] ?? '') ?>" required>
                </div>
                <div class="form-group grow">
                    <label for="tkey">Schlüssel (für <code>{{template:…}}</code>)</label>
                    <input type="text" id="tkey" name="tkey" value="<?= e($template['tkey'] ?? '') ?>" placeholder="z. B. main-menu">
                </div>
            </div>
            <div class="form-group">
                <label for="html">HTML</label>
                <textarea id="html" name="html" class="code" rows="16" spellcheck="false" required><?= e($template['html'] ?? '') ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Speichern' : 'Template anlegen' ?></button>
                <a class="btn btn-ghost" href="<?= e(url('/admin/templates')) ?>">Abbrechen</a>
            </div>
        </form>
    </div>
    <aside class="card help-card">
        <h3>Menü-Vorlagen</h3>
        <p class="muted small">Das Menü wird automatisch aus deiner Seiten-Baumstruktur aufgebaut (Unterpunkte über „Übergeordnete Seite“ bei den Seiten). Wähle die Darstellung:</p>
        <div class="menu-insert-buttons">
            <button type="button" class="btn btn-small" data-insert="{{menu}}">Dropdown (Hover)</button>
            <button type="button" class="btn btn-small" data-insert="{{menu:mega}}">Mega-Menü</button>
            <button type="button" class="btn btn-small" data-insert="{{menu:vertical}}">Vertikal (Sidebar)</button>
            <button type="button" class="btn btn-small" data-insert="{{menu:simple}}">Nur oberste Ebene</button>
        </div>
        <p class="muted small">Klick fügt den Platzhalter an der Cursor-Position im HTML ein.</p>

        <h3>Platzhalter</h3>
        <ul class="placeholder-list">
            <li><code>{{menu}}</code> – Hover-Aufklappmenü (Standard)</li>
            <li><code>{{menu:mega}}</code> – Mega-Menü mit Spalten-Panel</li>
            <li><code>{{menu:vertical}}</code> – vertikale Baum-Liste</li>
            <li><code>{{menu:simple}}</code> – nur oberste Ebene</li>
            <li><code>{{site_name}}</code> – Name der Website</li>
            <li><code>{{base_url}}</code> – Basis-URL</li>
            <li><code>{{year}}</code> – aktuelles Jahr</li>
            <li><code>{{template:key}}</code> – anderes Template einbetten</li>
            <li><code>{{global:ID}}</code> – globalen Block einbetten (ID: siehe „Globale Blöcke“)</li>
            <li><code>{{languages}}</code> – Sprachumschalter</li>
        </ul>
        <p class="muted small">Templates sind wiederverwendbare Bausteine, die du mit <code>{{template:key}}</code> in Layouts (oder anderen Templates) einbettest – z.&nbsp;B. Hauptmenü, Footer oder ein Kontakt-Kasten.</p>
    </aside>
</div>

<script>
document.querySelectorAll('[data-insert]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const area = document.getElementById('html');
        const insert = btn.dataset.insert;
        const start = area.selectionStart ?? area.value.length;
        area.value = area.value.slice(0, start) + insert + area.value.slice(area.selectionEnd ?? start);
        area.focus();
        area.selectionStart = area.selectionEnd = start + insert.length;
    });
});
</script>
