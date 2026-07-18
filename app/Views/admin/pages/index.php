<div class="page-actions" style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
    <a class="btn btn-primary" href="<?= e(url('/admin/pages/new')) ?>">+ Neue Seite</a>
    <a class="btn btn-ghost" href="<?= e(url('/admin/pages/trash')) ?>">🗑 Papierkorb</a>
</div>

<?php
// Flache Baum-Liste nach Eltern gruppieren, um verschachtelt zu rendern.
$byParent = [];
foreach ($pages as $page) {
    $byParent[(int) ($page['parent_id'] ?? 0)][] = $page;
}

$renderItem = function (array $page) use (&$renderItem, $byParent, $layouts): void {
    $id = (int) $page['id'];
    ?>
    <li class="pt-item" data-id="<?= $id ?>">
        <div class="pt-row" draggable="true">
            <span class="pt-handle" title="Zum Verschieben ziehen" aria-hidden="true">⠿</span>
            <div class="pt-main">
                <a class="pt-title" href="<?= e(url('/admin/pages/' . $id . '/editor')) ?>"><?= e($page['title']) ?></a>
                <code class="pt-slug">/<?= e($page['slug']) ?></code>
            </div>
            <div class="pt-meta">
                <span class="pt-layout muted"><?= e($layouts[(int) ($page['layout_id'] ?? 0)] ?? '–') ?></span>
                <?= (int) $page['in_menu'] ? '<span class="badge badge-green">Menü</span>' : '<span class="badge">kein Menü</span>' ?>
                <?= (int) $page['published'] ? '<span class="badge badge-green">Veröffentlicht</span>' : '<span class="badge badge-amber">Entwurf</span>' ?>
            </div>
            <div class="pt-actions actions-col">
                <a class="btn btn-small" href="<?= e(url('/admin/pages/' . $id . '/editor')) ?>">Inhalt</a>
                <a class="btn btn-small btn-ghost" href="<?= e(url('/admin/pages/' . $id . '/edit')) ?>">Eigenschaften</a>
                <form method="post" action="<?= e(url('/admin/pages/' . $id . '/duplicate')) ?>" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-small btn-ghost" title="Seite als Entwurf duplizieren">⧉</button>
                </form>
                <form method="post" action="<?= e(url('/admin/pages/' . $id . '/delete')) ?>" class="inline" onsubmit="return confirm('Seite „<?= e($page['title']) ?>“ in den Papierkorb verschieben?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-small btn-danger">Löschen</button>
                </form>
            </div>
        </div>
        <ul class="pt-children">
            <?php foreach ($byParent[$id] ?? [] as $child) { $renderItem($child); } ?>
        </ul>
    </li>
    <?php
};
?>

<div class="card">
    <?php if (empty($pages)): ?>
        <p class="muted">Noch keine Seiten vorhanden. Lege deine erste Seite an!</p>
    <?php else: ?>
        <p class="pt-hint muted small">Tipp: Seiten per <strong>⠿</strong> ziehen, um die Reihenfolge zu ändern. Auf eine Seite ziehen macht sie zur Unterseite; an den linken Rand ziehen macht sie wieder zur Hauptseite.</p>
        <ul class="page-tree" id="page-tree" data-reorder-url="<?= e(url('/admin/pages/reorder')) ?>" data-csrf="<?= e(csrf_token()) ?>">
            <?php foreach ($byParent[0] ?? [] as $page) { $renderItem($page); } ?>
        </ul>
        <p class="pt-status small" id="pt-status" role="status" aria-live="polite"></p>
    <?php endif; ?>
</div>

<script src="<?= e(asset('/assets/js/page-tree.js')) ?>"></script>
