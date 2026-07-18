<div class="page-actions" style="display:flex;gap:10px;flex-wrap:wrap">
    <form method="post" action="<?= e(url('/admin/layouts/visual-new')) ?>" class="inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary">+ Neues Layout (visuell, per Drag &amp; Drop)</button>
    </form>
    <a class="btn btn-ghost" href="<?= e(url('/admin/layouts/new')) ?>">+ Neues Layout (HTML)</a>
</div>

<div class="card">
    <?php if (empty($layouts)): ?>
        <p class="muted">Noch keine Layouts vorhanden.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Name</th><th>Zuletzt geändert</th><th class="actions-col">Aktionen</th></tr>
            </thead>
            <tbody>
                <?php foreach ($layouts as $layout): ?>
                    <?php $isVisual = !empty($layout['builder']) && str_contains((string) $layout['builder'], '"rows"'); ?>
                    <?php $isDefault = (int) ($layout['is_default'] ?? 0) === 1; ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/admin/layouts/' . $layout['id'] . ($isVisual ? '/builder' : '/edit'))) ?>"><strong><?= e($layout['name']) ?></strong></a>
                            <?= $isVisual ? '<span class="badge badge-green">Visuell</span>' : '<span class="badge">HTML</span>' ?>
                            <?php if ($isDefault): ?><span class="badge badge-orange" title="Wird bei neuen Seiten vorgewählt">★ Standard</span><?php endif; ?>
                        </td>
                        <td class="muted"><?= e($layout['updated_at']) ?></td>
                        <td class="actions-col">
                            <?php if (!$isDefault): ?>
                                <form method="post" action="<?= e(url('/admin/layouts/' . $layout['id'] . '/make-default')) ?>" class="inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-small btn-ghost" title="Bei neuen Seiten vorwählen">★ Als Standard</button>
                                </form>
                            <?php endif; ?>
                            <a class="btn btn-small btn-primary" href="<?= e(url('/admin/layouts/' . $layout['id'] . '/builder')) ?>">Visuell bearbeiten</a>
                            <a class="btn btn-small btn-ghost" href="<?= e(url('/admin/layouts/' . $layout['id'] . '/edit')) ?>">HTML &amp; Design</a>
                            <?php if ($isVisual): ?>
                                <form method="post" action="<?= e(url('/admin/layouts/' . $layout['id'] . '/builder-reset')) ?>" class="inline" onsubmit="return confirm('Visuellen Modus deaktivieren? Das Layout nutzt dann wieder sein HTML; die Baukasten-Struktur geht verloren.')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-small btn-ghost" title="Zurück zum HTML-Modus">→ HTML</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/admin/layouts/' . $layout['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('Layout „<?= e($layout['name']) ?>“ wirklich löschen?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger">Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
