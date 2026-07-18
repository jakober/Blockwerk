<div class="card narrow">
    <h2>Neuer globaler Block</h2>
    <p class="muted small">Globale Blöcke sind wiederverwendbare Inhaltsbereiche (z.&nbsp;B. eine Kontakt-Box oder ein Banner). Du pflegst sie einmal hier – und setzt sie über den Block „Globaler Block“ auf beliebig vielen Seiten ein. Eine Änderung wirkt überall.</p>
    <form method="post" action="<?= e(url('/admin/globals')) ?>" class="upload-form">
        <?= csrf_field() ?>
        <input type="text" name="title" placeholder="Name, z. B. Kontakt-Box" required>
        <button type="submit" class="btn btn-primary">Anlegen &amp; bearbeiten</button>
    </form>
</div>

<div class="card">
    <?php if (empty($globals)): ?>
        <p class="muted">Noch keine globalen Blöcke vorhanden.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Name</th><th>Einbau</th><th>Zuletzt geändert</th><th class="actions-col">Aktionen</th></tr>
            </thead>
            <tbody>
                <?php foreach ($globals as $global): ?>
                    <tr>
                        <td><a href="<?= e(url('/admin/pages/' . $global['id'] . '/editor')) ?>"><strong><?= e($global['title']) ?></strong></a></td>
                        <td>
                            <code>{{global:<?= (int) $global['id'] ?>}}</code>
                            <div class="muted small">für Layout/Template (jede Seite) – oder im Editor den Block „Globaler Block“ nutzen (einzelne Seiten)</div>
                        </td>
                        <td class="muted"><?= e(format_date_de($global['updated_at'], true)) ?></td>
                        <td class="actions-col">
                            <a class="btn btn-small" href="<?= e(url('/admin/pages/' . $global['id'] . '/editor')) ?>">Inhalt bearbeiten</a>
                            <form method="post" action="<?= e(url('/admin/pages/' . $global['id'] . '/delete')) ?>" class="inline" data-confirm="„<?= e($global['title']) ?>“ in den Papierkorb verschieben? Er wird dann auf allen Seiten ausgeblendet." data-confirm-danger data-confirm-ok="In den Papierkorb">
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
