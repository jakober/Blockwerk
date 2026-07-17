<?php
$isEdit = $layout !== null;
$action = $isEdit ? '/admin/layouts/' . $layout['id'] : '/admin/layouts';
?>
<div class="editor-grid">
    <div class="card">
        <form method="post" action="<?= e(url($action)) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?= e($layout['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="html">HTML-Grundgerüst</label>
                <textarea id="html" name="html" class="code" rows="24" spellcheck="false" required><?= e($layout['html'] ?? "<!doctype html>\n<html lang=\"de\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>{{title}} – {{site_name}}</title>\n<link rel=\"stylesheet\" href=\"{{base_url}}/assets/css/site.css\">\n</head>\n<body>\n<header class=\"site-header\">\n  <div class=\"container header-inner\">\n    <a class=\"brand\" href=\"{{base_url}}/\">{{site_name}}</a>\n    {{template:main-menu}}\n  </div>\n</header>\n<main class=\"container\">\n{{content}}\n</main>\n<footer class=\"site-footer\">\n  <div class=\"container\">&copy; {{year}} {{site_name}}</div>\n</footer>\n</body>\n</html>") ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Speichern' : 'Layout anlegen' ?></button>
                <a class="btn btn-ghost" href="<?= e(url('/admin/layouts')) ?>">Abbrechen</a>
            </div>
        </form>
    </div>
    <aside class="card help-card">
        <h3>Platzhalter</h3>
        <ul class="placeholder-list">
            <li><code>{{content}}</code> – Seiteninhalt (Pflicht)</li>
            <li><code>{{title}}</code> – Seitentitel</li>
            <li><code>{{site_name}}</code> – Name der Website</li>
            <li><code>{{base_url}}</code> – Basis-URL</li>
            <li><code>{{year}}</code> – aktuelles Jahr</li>
            <li><code>{{menu}}</code> – Hauptmenü</li>
            <li><code>{{template:key}}</code> – Template einbetten</li>
        </ul>
        <p class="muted small">Layouts sind das HTML-Grundgerüst einer Seite. Templates (z.&nbsp;B. <code>{{template:main-menu}}</code>) sind wiederverwendbare Bausteine darin.</p>
    </aside>
</div>
