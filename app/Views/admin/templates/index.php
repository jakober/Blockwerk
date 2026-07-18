<div class="page-actions">
    <a class="btn btn-primary" href="<?= e(url('/admin/templates/new')) ?>">+ Neues Template</a>
</div>

<div class="card">
    <?php if (empty($templates)): ?>
        <p class="muted">Noch keine Templates vorhanden.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Name</th><th>Schlüssel</th><th>Zuletzt geändert</th><th class="actions-col">Aktionen</th></tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $template): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/admin/templates/' . $template['id'] . '/edit')) ?>"><strong><?= e($template['name']) ?></strong></a>
                            <?php if ($template['tkey'] === 'main-menu'): ?>
                                <span class="badge">wird vom <a href="<?= e(url('/admin/menu')) ?>">Menü-Designer</a> verwaltet</span>
                            <?php endif; ?>
                        </td>
                        <td><code>{{template:<?= e($template['tkey']) ?>}}</code></td>
                        <td class="muted"><?= e($template['updated_at']) ?></td>
                        <td class="actions-col">
                            <a class="btn btn-small" href="<?= e(url('/admin/templates/' . $template['id'] . '/edit')) ?>">Bearbeiten</a>
                            <form method="post" action="<?= e(url('/admin/templates/' . $template['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('Template „<?= e($template['name']) ?>“ wirklich löschen?')">
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
