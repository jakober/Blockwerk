<div class="page-actions">
    <a class="btn btn-ghost" href="<?= e(url('/admin/pages/' . $page['id'] . '/editor')) ?>">← Zurück zum Editor</a>
</div>

<div class="card">
    <p class="muted small">Bei jedem Speichern im Editor wird der vorherige Stand hier gesichert (die letzten 20 Versionen). Beim Wiederherstellen wird der aktuelle Stand ebenfalls als Version gesichert – es geht also nichts verloren.</p>
    <?php if (empty($versions)): ?>
        <p class="muted">Noch keine Versionen vorhanden – sie entstehen beim Speichern im Editor.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Gespeichert am</th><th>Von</th><th class="actions-col">Aktionen</th></tr>
            </thead>
            <tbody>
                <?php foreach ($versions as $version): ?>
                    <tr>
                        <td><strong><?= e(format_date_de($version['created_at'], true)) ?></strong></td>
                        <td class="muted"><?= e($version['username'] ?? '–') ?></td>
                        <td class="actions-col">
                            <form method="post" action="<?= e(url('/admin/pages/' . $page['id'] . '/versions/' . $version['id'] . '/restore')) ?>" class="inline" data-confirm="Diese Version wiederherstellen? Der aktuelle Stand wird vorher gesichert." data-confirm-ok="Wiederherstellen">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-primary">Wiederherstellen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
