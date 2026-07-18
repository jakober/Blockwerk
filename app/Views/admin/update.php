<?php
/** Markdown-Fett (**…**) und Code (`…`) aus dem Changelog hübsch darstellen. */
$fmt = static function (string $line): string {
    $line = e($line);
    $line = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line);
    return preg_replace('/`(.+?)`/', '<code>$1</code>', $line);
};
$updateAvailable = $remoteVersion !== null && version_compare($remoteVersion, $currentVersion, '>');
?>

<?php if (!empty($updateDone)): ?>
    <div class="card narrow update-success">
        <div class="update-check">✓</div>
        <h2>Update erfolgreich!</h2>
        <p class="muted">Blockwerk Orange wurde von Version <?= e($updateDone['from']) ?> auf
            <strong>Version <?= e($updateDone['to']) ?></strong> aktualisiert.<br>
            Deine Inhalte, Einstellungen und Uploads sind unverändert.</p>

        <?php if (!empty($changelog)): ?>
            <div class="update-news">
                <h3>Das ist neu</h3>
                <?php foreach ($changelog as $version => $entries): ?>
                    <p class="update-news-version">Version <?= e($version) ?></p>
                    <ul>
                        <?php foreach ($entries as $entry): ?>
                            <li><?= $fmt($entry) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a class="btn btn-primary" href="<?= e(url('/admin/update')) ?>">Alles klar</a>
    </div>
<?php endif; ?>

<div class="card narrow update-hero">
    <div class="update-logo"><?php $logoSize = 46; include APP_PATH . '/Views/_logo.php'; ?></div>
    <h2>Blockwerk Orange</h2>
    <p class="update-version">Version <?= e($currentVersion) ?></p>

    <?php if ($updateAvailable): ?>
        <div class="update-available">
            <p><strong>Version <?= e($remoteVersion) ?> ist verfügbar!</strong></p>
            <button type="button" class="btn btn-primary btn-big" id="update-open">Jetzt aktualisieren</button>
        </div>
    <?php elseif ($remoteVersion !== null): ?>
        <p class="badge badge-green update-badge">✓ Du bist auf dem neuesten Stand</p>
    <?php else: ?>
        <p class="muted small">Prüfe mit einem Klick, ob eine neue Version bereitsteht.</p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/admin/update/check')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn <?= $updateAvailable ? 'btn-ghost btn-small' : '' ?>">Nach Updates suchen</button>
    </form>
</div>

<div class="card narrow">
    <h2>Backup</h2>
    <p class="muted small">Lädt eine komplette Sicherung als ZIP herunter: <strong>Datenbank</strong> (alle Inhalte, Seiten, Einstellungen), <strong>Uploads</strong> (Medien &amp; Schriften) und die Konfigurationsdatei – inklusive Anleitung zur Wiederherstellung. Empfohlen vor jedem Update.</p>
    <form method="post" action="<?= e(url('/admin/backup')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn">Backup jetzt herunterladen</button>
    </form>
</div>

<?php if ($updateAvailable): ?>
<div class="modal-overlay" id="update-modal" hidden>
    <div class="modal">
        <h3>Auf Version <?= e($remoteVersion) ?> aktualisieren?</h3>
        <p class="muted">Das Update wird heruntergeladen und installiert.
            <strong>Deine Inhalte, Einstellungen und Uploads bleiben dabei erhalten.</strong><br>
            Tipp: Vorher schadet ein Backup nie.</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" id="update-cancel">Abbrechen</button>
            <form method="post" action="<?= e(url('/admin/update/run')) ?>" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary" id="update-run">Jetzt aktualisieren</button>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('update-modal');
    document.getElementById('update-open').addEventListener('click', function () { modal.hidden = false; });
    document.getElementById('update-cancel').addEventListener('click', function () { modal.hidden = true; });
    modal.addEventListener('click', function (e) { if (e.target === modal) { modal.hidden = true; } });
    document.getElementById('update-run').addEventListener('click', function () {
        this.disabled = true;
        this.textContent = 'Update läuft …';
        this.closest('form').submit();
    });
})();
</script>
<?php endif; ?>
