<div class="page-actions">
    <a class="btn btn-primary" href="<?= e(url('/admin/layouts/new')) ?>">+ Neues Layout</a>
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
                    <tr>
                        <td><a href="<?= e(url('/admin/layouts/' . $layout['id'] . '/edit')) ?>"><strong><?= e($layout['name']) ?></strong></a></td>
                        <td class="muted"><?= e($layout['updated_at']) ?></td>
                        <td class="actions-col">
                            <a class="btn btn-small" href="<?= e(url('/admin/layouts/' . $layout['id'] . '/edit')) ?>">Bearbeiten</a>
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
