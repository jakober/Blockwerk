<link rel="stylesheet" href="<?= e(url('/assets/css/cms-blocks.css')) ?>">
<?= $designHead ?>

<div id="editor"
     data-save-url="<?= e(url('/admin/pages/' . $page['id'] . '/content')) ?>"
     data-preview-url="<?= e(url('/admin/preview/blocks')) ?>"
     data-csrf="<?= e(csrf_token()) ?>">

    <div class="ed-topbar">
        <a class="btn btn-ghost" href="<?= e(url('/admin/pages')) ?>">← Seiten</a>
        <strong class="ed-title"><?= e($page['title']) ?></strong>
        <div class="ed-presets" title="Neue Zeile mit Spaltenaufteilung hinzufügen"></div>
        <span id="ed-status" class="ed-status"></span>
        <button type="button" id="ed-css-btn" class="btn btn-ghost" title="Eigenes CSS nur für diese Seite">CSS</button>
        <a class="btn btn-ghost" href="<?= e(url('/' . $page['slug'])) ?>" target="_blank" rel="noopener">Vorschau ↗</a>
        <button type="button" id="ed-save" class="btn btn-primary">Speichern</button>
    </div>

    <div class="ed-css-panel" hidden>
        <label>Eigenes CSS – gilt nur für diese Seite (optional)</label>
        <textarea id="ed-css-input" class="code" rows="7" spellcheck="false" placeholder="/* z. B. .cms-quote { font-size: 1.3em; } */"></textarea>
    </div>

    <div class="ed-main">
        <aside class="ed-palette">
            <h3>Inhalts-Blöcke</h3>
            <p class="muted small">Per Drag &amp; Drop in eine Spalte ziehen</p>
            <div class="ed-palette-items"></div>
        </aside>
        <div class="ed-canvas-wrap">
            <div class="ed-canvas cms-scope"></div>
        </div>
        <aside class="ed-inspector">
            <h3>Eigenschaften</h3>
            <div class="ed-inspector-body"><p class="muted small">Klicke auf einen Block, um ihn zu bearbeiten.</p></div>
        </aside>
    </div>
</div>

<style id="ed-page-css"></style>
<script type="application/json" id="editor-data"><?= $contentJson ?></script>
<script src="<?= e(url('/assets/js/admin-tools.js')) ?>"></script>
<script src="<?= e(url('/assets/js/editor.js')) ?>"></script>
