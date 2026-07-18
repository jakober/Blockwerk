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

<div class="card narrow">
    <h2>Wiederherstellen</h2>
    <p class="muted small">Spielt eine zuvor heruntergeladene Backup-ZIP wieder ein: <strong>Datenbank</strong> und <strong>Uploads</strong> (alle Inhalte, Seiten, Medien &amp; Einstellungen) werden auf den Stand der Sicherung zurückgesetzt. Die Konfiguration (Datenbank-Zugang) und die <strong>installierte Version</strong> bleiben unverändert.</p>
    <p class="restore-warn small"><strong>Achtung:</strong> Der aktuelle Stand wird dabei überschrieben. Am besten vorher ein frisches Backup herunterladen.</p>
    <form id="restore-form" data-chunk-url="<?= e(url('/admin/restore/chunk')) ?>" data-run-url="<?= e(url('/admin/restore/run')) ?>" data-csrf="<?= e(csrf_token()) ?>">
        <input type="file" id="restore-file" accept=".zip,application/zip" required>
        <button type="submit" class="btn btn-danger" id="restore-btn">Sicherung wiederherstellen</button>
    </form>
    <div class="restore-progress" id="restore-progress" hidden>
        <div class="restore-bar"><div class="restore-bar-fill" id="restore-bar-fill"></div></div>
        <div class="restore-progress-meta">
            <span id="restore-phase">Wird vorbereitet …</span>
            <span id="restore-eta" class="muted"></span>
        </div>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('restore-form');
    if (!form) return;
    var fileInput = document.getElementById('restore-file');
    var btn = document.getElementById('restore-btn');
    var box = document.getElementById('restore-progress');
    var fill = document.getElementById('restore-bar-fill');
    var phaseEl = document.getElementById('restore-phase');
    var etaEl = document.getElementById('restore-eta');
    var CHUNK = 1024 * 1024; // 1 MB pro Häppchen (unter allen Server-Limits)

    function setBar(frac, label) {
        var pct = Math.max(0, Math.min(100, Math.round(frac * 100)));
        fill.style.width = pct + '%';
        phaseEl.textContent = label + ' · ' + pct + ' %';
    }
    function etaText(startTs, frac) {
        if (frac <= 0.01) return '';
        var elapsed = (Date.now() - startTs) / 1000;
        var remain = elapsed * (1 - frac) / frac;
        if (!isFinite(remain) || remain < 1) return 'gleich fertig';
        if (remain < 60) return 'noch ca. ' + Math.ceil(remain) + ' s';
        return 'noch ca. ' + Math.ceil(remain / 60) + ' min';
    }

    async function uploadFile(file, token) {
        var total = file.size;
        var offset = 0, index = 0;
        var start = Date.now();
        var base = form.dataset.chunkUrl + '?token=' + token + '&index=';
        while (offset < total) {
            var slice = file.slice(offset, offset + CHUNK);
            var buf = await slice.arrayBuffer();
            var resp = await fetch(base + index, {
                method: 'POST',
                headers: { 'Content-Type': 'application/octet-stream', 'X-CSRF-Token': form.dataset.csrf },
                body: buf
            });
            if (!resp.ok) throw new Error('Upload-Fehler (' + resp.status + ')');
            offset += CHUNK; index++;
            var frac = Math.min(offset, total) / total;
            setBar(frac * 0.5, 'Hochladen'); // Upload ist die erste Hälfte des Balkens
            etaEl.textContent = etaText(start, frac);
        }
    }

    async function runRestore(token) {
        var start = Date.now();
        var resp = await fetch(form.dataset.runUrl + '?token=' + token, {
            method: 'POST',
            headers: { 'X-CSRF-Token': form.dataset.csrf }
        });
        if (!resp.ok || !resp.body) throw new Error('Server-Fehler (' + resp.status + ')');
        var reader = resp.body.getReader();
        var dec = new TextDecoder();
        var buf = '';
        while (true) {
            var r = await reader.read();
            if (r.done) break;
            buf += dec.decode(r.value, { stream: true });
            var nl;
            while ((nl = buf.indexOf('\n')) >= 0) {
                var line = buf.slice(0, nl).trim();
                buf = buf.slice(nl + 1);
                if (!line) continue;
                var ev = JSON.parse(line);
                if (ev.error) throw new Error(ev.error);
                if (ev.phase === 'done') {
                    setBar(1, 'Fertig');
                    etaEl.textContent = '';
                    return;
                }
                if (ev.total) {
                    var frac = (ev.done || 0) / ev.total;
                    setBar(0.5 + frac * 0.5, 'Einspielen'); // Einspielen ist die zweite Hälfte
                    etaEl.textContent = etaText(start, frac);
                }
            }
        }
    }

    function randToken() {
        var a = new Uint8Array(16);
        (window.crypto || {}).getRandomValues ? window.crypto.getRandomValues(a) : a.forEach(function (_, i) { a[i] = i; });
        return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        var file = fileInput.files[0];
        if (!file) { window.AdminDialog.alert('Bitte zuerst eine Backup-ZIP auswählen.'); return; }

        var ok = await window.AdminDialog.confirm(
            'Wirklich diese Sicherung einspielen? Alle aktuellen Inhalte, Seiten und Medien werden durch den Stand der Backup-Datei ersetzt. Die installierte Version bleibt erhalten.',
            { danger: true, confirmText: 'Wiederherstellen' }
        );
        if (!ok) return;

        btn.disabled = true;
        fileInput.disabled = true;
        box.hidden = false;
        setBar(0, 'Hochladen');
        etaEl.textContent = '';

        try {
            var token = randToken();
            await uploadFile(file, token);
            await runRestore(token);
            phaseEl.textContent = '✓ Wiederherstellung abgeschlossen';
            await window.AdminDialog.alert('Die Sicherung wurde erfolgreich eingespielt. Alle Inhalte, Seiten und Medien entsprechen jetzt dem Backup. Die installierte Version ist unverändert.', { title: 'Fertig' });
            window.location.reload();
        } catch (err) {
            phaseEl.textContent = 'Fehler';
            etaEl.textContent = '';
            btn.disabled = false;
            fileInput.disabled = false;
            box.hidden = true;
            window.AdminDialog.alert('Wiederherstellung fehlgeschlagen: ' + (err && err.message ? err.message : err));
        }
    });
})();
</script>

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
