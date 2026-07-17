<div class="page-actions">
    <a class="btn btn-ghost" href="<?= e(url('/admin/pages')) ?>">← Zurück zu den Seiten</a>
</div>

<div class="card">
    <?php if (empty($pages)): ?>
        <p class="muted">Der Papierkorb ist leer.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Titel</th><th>Typ</th><th>Gelöscht am</th><th class="actions-col">Aktionen</th></tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $page): ?>
                    <tr>
                        <td><strong><?= e($page['title']) ?></strong> <span class="muted small">/<?= e($page['slug']) ?></span></td>
                        <td><?= (int) $page['is_global'] ? '<span class="badge">Globaler Block</span>' : '<span class="badge">Seite</span>' ?></td>
                        <td class="muted"><?= e(format_date_de($page['deleted_at'], true)) ?></td>
                        <td class="actions-col">
                            <form method="post" action="<?= e(url('/admin/pages/' . $page['id'] . '/restore')) ?>" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-primary">Wiederherstellen</button>
                            </form>
                            <form method="post" action="<?= e(url('/admin/pages/' . $page['id'] . '/destroy')) ?>" class="inline" onsubmit="return confirm('„<?= e($page['title']) ?>“ ENDGÜLTIG löschen? Das kann nicht rückgängig gemacht werden.')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger">Endgültig löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
