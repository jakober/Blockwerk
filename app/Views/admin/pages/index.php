<div class="page-actions">
    <a class="btn btn-primary" href="<?= e(url('/admin/pages/new')) ?>">+ Neue Seite</a>
</div>

<div class="card">
    <?php if (empty($pages)): ?>
        <p class="muted">Noch keine Seiten vorhanden. Lege deine erste Seite an!</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Slug</th>
                    <th>Layout</th>
                    <th>Im Menü</th>
                    <th>Status</th>
                    <th class="actions-col">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $page): ?>
                    <tr>
                        <td>
                            <span class="tree-indent" style="--depth:<?= (int) $page['depth'] ?>"></span>
                            <a href="<?= e(url('/admin/pages/' . $page['id'] . '/editor')) ?>"><strong><?= e($page['title']) ?></strong></a>
                        </td>
                        <td><code>/<?= e($page['slug']) ?></code></td>
                        <td><?= e($layouts[(int) ($page['layout_id'] ?? 0)] ?? '–') ?></td>
                        <td><?= (int) $page['in_menu'] ? '<span class="badge badge-green">Ja</span>' : '<span class="badge">Nein</span>' ?></td>
                        <td><?= (int) $page['published'] ? '<span class="badge badge-green">Veröffentlicht</span>' : '<span class="badge badge-amber">Entwurf</span>' ?></td>
                        <td class="actions-col">
                            <a class="btn btn-small" href="<?= e(url('/admin/pages/' . $page['id'] . '/editor')) ?>">Inhalt</a>
                            <a class="btn btn-small btn-ghost" href="<?= e(url('/admin/pages/' . $page['id'] . '/edit')) ?>">Eigenschaften</a>
                            <form method="post" action="<?= e(url('/admin/pages/' . $page['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('Seite „<?= e($page['title']) ?>“ wirklich löschen?')">
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
