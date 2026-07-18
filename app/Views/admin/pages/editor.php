<?php
/**
 * WYSIWYG-Editor – genutzt für Seiteninhalte UND den visuellen
 * Layout-Baukasten. Aufrufende Controller liefern:
 * $saveUrl, $backUrl, $backLabel, $previewHref (oder null),
 * $versionsUrl (oder null), $mode ('page' | 'layout'), $editorTitle,
 * $contentJson, $designHead, $globalBlocks.
 */
$mode = $mode ?? 'page';
?>
<link rel="stylesheet" href="<?= e(asset('/assets/css/cms-blocks.css')) ?>">
<?= $designHead ?>

<div id="editor"
     data-save-url="<?= e($saveUrl) ?>"
     data-preview-url="<?= e(url('/admin/preview/blocks')) ?>"
     data-mode="<?= e($mode) ?>"
     data-csrf="<?= e(csrf_token()) ?>">

    <div class="ed-topbar">
        <a class="btn btn-ghost" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a>
        <strong class="ed-title"><?= e($editorTitle) ?></strong>
        <div class="ed-presets" title="Neue Zeile mit Spaltenaufteilung hinzufügen"></div>
        <div class="ed-devices" title="Ansicht: So sieht die Seite auf Desktop, Tablet oder Smartphone aus">
            <button type="button" data-device="desktop" class="is-active" title="Desktop-Ansicht">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
            </button>
            <button type="button" data-device="tablet" title="Tablet-Ansicht (768 px, Spalten untereinander)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M10.5 18h3"/></svg>
            </button>
            <button type="button" data-device="phone" title="Smartphone-Ansicht (400 px, Spalten untereinander)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>
            </button>
        </div>
        <span id="ed-status" class="ed-status"></span>
        <?php if (!empty($versionsUrl)): ?>
            <a class="btn btn-ghost" href="<?= e($versionsUrl) ?>" title="Frühere Stände wiederherstellen">Versionen</a>
        <?php endif; ?>
        <button type="button" id="ed-css-btn" class="btn btn-ghost" title="Eigenes CSS (<?= $mode === 'layout' ? 'für dieses Layout' : 'nur für diese Seite' ?>)">CSS</button>
        <?php if (!empty($previewHref)): ?>
            <a class="btn btn-ghost" href="<?= e($previewHref) ?>" target="_blank" rel="noopener">Vorschau ↗</a>
        <?php endif; ?>
        <button type="button" id="ed-save" class="btn btn-primary">Speichern</button>
    </div>

    <div class="ed-css-panel" hidden>
        <label>Eigenes CSS – <?= $mode === 'layout' ? 'gilt auf allen Seiten mit diesem Layout' : 'gilt nur für diese Seite' ?> (optional)</label>
        <textarea id="ed-css-input" class="code" rows="7" spellcheck="false" placeholder="/* z. B. .cms-quote { font-size: 1.3em; } */"></textarea>
    </div>

    <div class="ed-main">
        <aside class="ed-palette">
            <h3><?= $mode === 'layout' ? 'Layout- & Inhalts-Blöcke' : 'Inhalts-Blöcke' ?></h3>
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
<script>window.CMS_GLOBAL_BLOCKS = <?= json_encode($globalBlocks ?? [], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;</script>
<script type="application/json" id="editor-data"><?= $contentJson ?></script>
<script src="<?= e(asset('/assets/js/admin-tools.js')) ?>"></script>
<script src="<?= e(asset('/assets/js/editor.js')) ?>"></script>
