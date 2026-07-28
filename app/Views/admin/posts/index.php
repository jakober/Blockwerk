<div class="page-actions">
    <a class="btn btn-primary" href="<?= e(url($basePath . '/new')) ?>">+ <?= e($labelSingular) ?></a>
</div>

<div class="card">
    <?php if (empty($posts)): ?>
        <p class="muted">Noch keine Einträge vorhanden.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Titel</th>
                    <th><?= $type === 'event' ? 'Termin' : 'Datum' ?></th>
                    <?php if ($type === 'event'): ?><th>Ort</th><?php endif; ?>
                    <th>Status</th>
                    <th class="actions-col">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td data-label="Titel"><a href="<?= e(url($basePath . '/' . $post['id'] . '/edit')) ?>"><strong><?= e($post['title']) ?></strong></a></td>
                        <td class="muted" data-label="<?= $type === 'event' ? 'Termin' : 'Datum' ?>">
                            <?= $type === 'event'
                                ? e(format_date_de($post['start_at'] ?? null, true))
                                : e(format_date_de($post['published_at'] ?? $post['created_at'] ?? null)) ?>
                        </td>
                        <?php if ($type === 'event'): ?><td class="muted" data-label="Ort"><?= e($post['location'] ?? '') ?></td><?php endif; ?>
                        <td data-label="Status"><?= (int) $post['published'] ? '<span class="badge badge-green">Veröffentlicht</span>' : '<span class="badge badge-amber">Entwurf</span>' ?></td>
                        <td class="actions-col">
                            <a class="btn btn-small" href="<?= e(url($basePath . '/' . $post['id'] . '/edit')) ?>">Bearbeiten</a>
                            <a class="btn btn-small btn-ghost" href="<?= e(url('/' . ($type === 'event' ? 'events' : 'news') . '/' . $post['slug'])) ?>" target="_blank" rel="noopener">Ansehen ↗</a>
                            <form method="post" action="<?= e(url($basePath . '/' . $post['id'] . '/delete')) ?>" class="inline" data-confirm="„<?= e($post['title']) ?>“ wirklich löschen?" data-confirm-danger data-confirm-ok="Löschen">
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
