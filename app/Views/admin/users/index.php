<div class="page-actions">
    <a class="btn btn-primary" href="<?= e(url('/admin/users/new')) ?>">+ Neuer Benutzer</a>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr><th>Benutzername</th><th>Angelegt am</th><th class="actions-col">Aktionen</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <strong><?= e($user['username']) ?></strong>
                        <?= (int) $user['id'] === $ownId ? '<span class="badge badge-green">Das bist du</span>' : '' ?>
                        <?= ($user['role'] ?? 'admin') === 'editor' ? '<span class="badge">Redakteur</span>' : '<span class="badge badge-amber">Admin</span>' ?>
                    </td>
                    <td class="muted"><?= e(format_date_de($user['created_at'])) ?></td>
                    <td class="actions-col">
                        <a class="btn btn-small" href="<?= e(url('/admin/users/' . $user['id'] . '/edit')) ?>"><?= (int) $user['id'] === $ownId ? 'Profil / Passwort ändern' : 'Bearbeiten' ?></a>
                        <?php if ((int) $user['id'] !== $ownId): ?>
                            <form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/delete')) ?>" class="inline" data-confirm="Benutzer „<?= e($user['username']) ?>“ wirklich löschen?" data-confirm-danger data-confirm-ok="Löschen">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger">Löschen</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="muted small">Alle Benutzer haben vollen Zugriff auf den Admin-Bereich. Jeder kann sein eigenes Passwort über „Profil / Passwort ändern“ anpassen.</p>
</div>
