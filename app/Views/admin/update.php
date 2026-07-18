<div class="card narrow">
    <h2>Version</h2>
    <p>Installiert: <strong>Version <?= e($currentVersion) ?></strong>
    <?php if ($remoteVersion !== null): ?>
        · Verfügbar: <strong>Version <?= e($remoteVersion) ?></strong>
        <?php if (version_compare($remoteVersion, $currentVersion, '>')): ?>
            <span class="badge badge-amber">Update verfügbar</span>
        <?php else: ?>
            <span class="badge badge-green">Aktuell</span>
        <?php endif; ?>
    <?php endif; ?>
    </p>

    <form method="post" action="<?= e(url('/admin/update/check')) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="zip_url">Update-Paket (ZIP-URL)</label>
            <input type="text" id="zip_url" name="zip_url" value="<?= e($zipUrl) ?>">
        </div>
        <div class="form-group">
            <label for="version_url">Versions-Datei (URL zur VERSION-Datei)</label>
            <input type="text" id="version_url" name="version_url" value="<?= e($versionUrl) ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Nach Updates suchen</button>
        </div>
    </form>
</div>

<?php if ($remoteVersion !== null && version_compare($remoteVersion, $currentVersion, '>')): ?>
<div class="card narrow">
    <h2>Update installieren</h2>
    <p class="muted small">Das Update lädt das aktuelle Paket herunter und überschreibt die Programmdateien.
    <strong>Deine Inhalte (Datenbank), die Konfiguration und alle Uploads bleiben erhalten.</strong>
    Trotzdem empfiehlt sich vorher ein Backup von Dateien und Datenbank.</p>
    <form method="post" action="<?= e(url('/admin/update/run')) ?>" onsubmit="return confirm('Update jetzt installieren?')">
        <?= csrf_field() ?>
        <input type="hidden" name="zip_url" value="<?= e($zipUrl) ?>">
        <input type="hidden" name="version_url" value="<?= e($versionUrl) ?>">
        <button type="submit" class="btn btn-primary">Jetzt auf Version <?= e($remoteVersion) ?> aktualisieren</button>
    </form>
</div>
<?php endif; ?>

<div class="card narrow">
    <h2>Backup</h2>
    <p class="muted small">Lädt eine komplette Sicherung als ZIP herunter: <strong>Datenbank</strong> (alle Inhalte, Seiten, Einstellungen), <strong>Uploads</strong> (Medien &amp; Schriften) und die Konfigurationsdatei – inklusive Anleitung zur Wiederherstellung. Empfohlen vor jedem Update.</p>
    <form method="post" action="<?= e(url('/admin/backup')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary">Backup jetzt herunterladen</button>
    </form>
</div>

<div class="card narrow">
    <h2>So funktioniert's</h2>
    <ol class="quickstart">
        <li>„Nach Updates suchen“ vergleicht deine installierte Version mit der VERSION-Datei im Repository.</li>
        <li>„Aktualisieren“ lädt das ZIP-Paket herunter und ersetzt die Programmdateien. Geschützt bleiben: <code>config/</code> (Zugangsdaten) und <code>public/uploads/</code> (Medien &amp; Schriften).</li>
        <li>Neue Datenbank-Tabellen werden automatisch angelegt.</li>
    </ol>
    <p class="muted small">Voraussetzung für die automatische Prüfung: Das Repository ist öffentlich erreichbar – ansonsten hier eigene URLs (z.&nbsp;B. zu deinem eigenen Server) hinterlegen.</p>
    <p class="muted small">Was sich pro Version geändert hat, steht im <a href="https://github.com/jakober/Blockwerk/blob/main/CHANGELOG.md" target="_blank" rel="noopener">Changelog ↗</a>.</p>
</div>
