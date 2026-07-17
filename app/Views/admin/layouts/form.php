<?php
$isEdit = $layout !== null;
$action = $isEdit ? '/admin/layouts/' . $layout['id'] : '/admin/layouts';
$colors = $design['colors'] ?? [];
$designFonts = $design['fonts'] ?? [];
$colorDefaults = [
    'primary' => ['#4f46e5', 'Primärfarbe', 'Buttons, Links, Menü-Akzente'],
    'accent' => ['#f59e0b', 'Akzentfarbe', 'Hervorhebungen, zweite Buttons'],
    'text' => ['#1e293b', 'Textfarbe', 'Fließtext'],
    'bg' => ['#ffffff', 'Hintergrund', 'Seitenhintergrund'],
    'surface' => ['#f1f5f9', 'Flächen', 'Boxen, Karten, Info-Kästen'],
];
?>
<form method="post" action="<?= e(url($action)) ?>">
    <?= csrf_field() ?>
    <div class="editor-grid">
        <div>
            <div class="card">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="<?= e($layout['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="html">HTML-Grundgerüst</label>
                    <textarea id="html" name="html" class="code" rows="22" spellcheck="false" required><?= e($layout['html'] ?? "<!doctype html>\n<html lang=\"de\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>{{title}} – {{site_name}}</title>\n<link rel=\"stylesheet\" href=\"{{base_url}}/assets/css/site.css\">\n</head>\n<body>\n<header class=\"site-header\">\n  <div class=\"container header-inner\">\n    <a class=\"brand\" href=\"{{base_url}}/\">{{site_name}}</a>\n    {{template:main-menu}}\n  </div>\n</header>\n<main class=\"container\">\n{{content}}\n</main>\n<footer class=\"site-footer\">\n  <div class=\"container\">&copy; {{year}} {{site_name}}</div>\n</footer>\n</body>\n</html>") ?></textarea>
                </div>
            </div>

            <div class="card">
                <h2>Design</h2>
                <p class="muted small">Diese Farben und Schriften gelten für alle Seiten mit diesem Layout. Alle Inhaltselemente und ihre Designvorlagen richten sich automatisch danach.</p>
                <div class="color-grid">
                    <?php foreach ($colorDefaults as $key => [$default, $label, $hint]): ?>
                        <div class="color-field">
                            <input type="color" id="color-<?= $key ?>" name="design[colors][<?= $key ?>]" value="<?= e($colors[$key] ?? $default) ?>">
                            <div>
                                <label for="color-<?= $key ?>"><?= e($label) ?></label>
                                <div class="muted small"><?= e($hint) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-row" style="margin-top:16px">
                    <div class="form-group grow">
                        <label for="font-heading">Schrift für Überschriften</label>
                        <select id="font-heading" name="design[fonts][heading]">
                            <option value="0">Systemschrift</option>
                            <?php foreach ($fonts as $font): ?>
                                <option value="<?= (int) $font['id'] ?>" <?= (int) ($designFonts['heading'] ?? 0) === (int) $font['id'] ? 'selected' : '' ?>><?= e($font['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group grow">
                        <label for="font-body">Schrift für Fließtext</label>
                        <select id="font-body" name="design[fonts][body]">
                            <option value="0">Systemschrift</option>
                            <?php foreach ($fonts as $font): ?>
                                <option value="<?= (int) $font['id'] ?>" <?= (int) ($designFonts['body'] ?? 0) === (int) $font['id'] ? 'selected' : '' ?>><?= e($font['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php if (empty($fonts)): ?>
                    <p class="muted small">Noch keine Schriften installiert – unter <a href="<?= e(url('/admin/fonts')) ?>">Schriften</a> kannst du Google Fonts herunterladen und lokal einbinden.</p>
                <?php endif; ?>
                <div class="form-group" style="margin-top:16px">
                    <label for="design-css">Eigenes CSS (optional – wird auf allen Seiten mit diesem Layout geladen)</label>
                    <textarea id="design-css" name="design[css]" class="code" rows="8" spellcheck="false" placeholder="/* z. B. .cms-heading { text-transform: uppercase; } */"><?= e($design['css'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Speichern' : 'Layout anlegen' ?></button>
                <a class="btn btn-ghost" href="<?= e(url('/admin/layouts')) ?>">Abbrechen</a>
            </div>
        </div>

        <aside class="card help-card">
            <h3>Platzhalter</h3>
            <ul class="placeholder-list">
                <li><code>{{content}}</code> – Seiteninhalt (Pflicht)</li>
                <li><code>{{title}}</code> – Seitentitel</li>
                <li><code>{{site_name}}</code> – Name der Website</li>
                <li><code>{{base_url}}</code> – Basis-URL</li>
                <li><code>{{year}}</code> – aktuelles Jahr</li>
                <li><code>{{menu}}</code> – Hauptmenü (Hover-Dropdown)</li>
                <li><code>{{menu:mega}}</code> / <code>{{menu:vertical}}</code> / <code>{{menu:simple}}</code> – weitere Menü-Vorlagen</li>
                <li><code>{{languages}}</code> – Sprachumschalter</li>
                <li><code>{{template:key}}</code> – Template einbetten</li>
                <li><code>{{global:ID}}</code> – globalen Block einbetten (ID: siehe „Globale Blöcke“)</li>
            </ul>
            <p class="muted small">Layouts sind das HTML-Grundgerüst einer Seite. Templates (z.&nbsp;B. <code>{{template:main-menu}}</code>) sind wiederverwendbare Bausteine darin.</p>
            <p class="muted small">Die gewählten Farben stehen im Layout-HTML und in eigenen Styles als CSS-Variablen zur Verfügung: <code>var(--cms-primary)</code>, <code>--cms-accent</code>, <code>--cms-text</code>, <code>--cms-bg</code>, <code>--cms-surface</code>, <code>--cms-font-heading</code>, <code>--cms-font-body</code>.</p>
        </aside>
    </div>
</form>
