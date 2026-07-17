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
        <h3>Platzhalter</h3>
        <ul class="placeholder-list">
            <li><code>{{menu}}</code> – Hauptmenü aus den Seiten</li>
            <li><code>{{site_name}}</code> – Name der Website</li>
            <li><code>{{base_url}}</code> – Basis-URL</li>
            <li><code>{{year}}</code> – aktuelles Jahr</li>
            <li><code>{{template:key}}</code> – anderes Template einbetten</li>
        </ul>
        <p class="muted small">Templates sind wiederverwendbare Bausteine, die du mit <code>{{template:key}}</code> in Layouts (oder anderen Templates) einbettest – z.&nbsp;B. Hauptmenü, Footer oder ein Kontakt-Kasten.</p>
    </aside>
</div>
