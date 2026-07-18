<div class="card">
    <div class="dropzone" id="dropzone" tabindex="0" role="button" aria-label="Dateien hochladen">
        <div class="dz-inner">
            <div class="dz-icon">⬆</div>
            <strong>Dateien hierher ziehen</strong>
            <span class="muted">oder klicken zum Auswählen<span id="dz-target"></span></span>
            <span class="muted small">Erlaubt: JPG, PNG, GIF, WebP, SVG, PDF · max. <?= e((string) $maxUpload) ?> pro Datei</span>
        </div>
        <div class="dz-progress" hidden>
            <div class="dz-bar"><span></span></div>
            <div class="dz-label muted small"></div>
        </div>
    </div>
    <input type="file" id="dz-input" multiple accept="image/*,.pdf" hidden>
    <div id="dz-message" hidden></div>
</div>

<div class="card">
    <div class="media-toolbar">
        <div class="media-folders" id="media-folders">
            <button type="button" class="media-chip is-active" data-folder="all">Alle Dateien</button>
            <?php foreach ($folders as $folder): ?>
                <button type="button" class="media-chip" data-folder="<?= (int) $folder['id'] ?>">📁 <?= e($folder['name']) ?></button>
            <?php endforeach; ?>
            <form method="post" action="<?= e(url('/admin/media/folders')) ?>" class="media-newfolder">
                <?= csrf_field() ?>
                <input type="text" name="name" placeholder="Neuer Ordner …">
                <button type="submit" class="btn btn-small">+ Ordner</button>
            </form>
        </div>
        <div class="media-search">
            <input type="search" id="media-search" placeholder="🔍 Dateien durchsuchen …">
        </div>
    </div>
    <div class="media-folder-tools" id="media-folder-tools" hidden>
        <form method="post" class="inline" id="folder-rename-form">
            <?= csrf_field() ?>
            <input type="text" name="name" id="folder-rename-name" placeholder="Neuer Name">
            <button type="submit" class="btn btn-small">Umbenennen</button>
        </form>
        <form method="post" class="inline" id="folder-delete-form" data-confirm="Ordner löschen? Die Dateien bleiben erhalten und liegen dann unter „Alle Dateien“." data-confirm-danger data-confirm-ok="Ordner löschen">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-small btn-danger">Ordner löschen</button>
        </form>
    </div>

    <p class="muted" id="media-empty" <?= empty($media) ? '' : 'hidden' ?>>Keine Dateien gefunden.</p>
    <div class="media-grid" id="media-grid">
        <?php foreach ($media as $item): ?>
            <?php $fileUrl = url('/' . $item['path']); ?>
            <div class="media-item"
                 data-id="<?= (int) $item['id'] ?>"
                 data-folder="<?= (int) ($item['folder_id'] ?? 0) ?>"
                 data-search="<?= e(mb_strtolower($item['filename'] . ' ' . ($item['alt'] ?? '') . ' ' . ($item['title'] ?? ''))) ?>"
                 data-name="<?= e($item['filename']) ?>"
                 data-alt="<?= e($item['alt'] ?? '') ?>"
                 data-title="<?= e($item['title'] ?? '') ?>"
                 data-url="<?= e($fileUrl) ?>">
                <div class="media-thumb" data-edit title="Bearbeiten">
                    <?php if (str_starts_with($item['mime'], 'image/')): ?>
                        <img src="<?= e(\Controllers\Admin\MediaController::thumbUrl($item['path'])) ?>" alt="<?= e($item['filename']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="media-file-icon">📄</span>
                    <?php endif; ?>
                </div>
                <div class="media-name" title="<?= e($item['filename']) ?>"><?= e($item['filename']) ?></div>
                <div class="media-meta muted small">
                    <?= $item['width'] ? (int) $item['width'] . '×' . (int) $item['height'] . ' · ' : '' ?><?= number_format((int) $item['size'] / 1024, 0, ',', '.') ?> KB
                </div>
                <div class="media-actions">
                    <button type="button" class="btn btn-small" data-edit>✎</button>
                    <button type="button" class="btn btn-small" data-copy="<?= e($fileUrl) ?>">URL</button>
                    <form method="post" action="<?= e(url('/admin/media/' . $item['id'] . '/delete')) ?>" class="inline" data-confirm="Datei „<?= e($item['filename']) ?>“ wirklich löschen?" data-confirm-danger data-confirm-ok="Löschen">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-small btn-danger">✕</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Bearbeiten-Dialog -->
<div class="modal-overlay" id="media-modal" hidden>
    <div class="modal">
        <h3>Datei bearbeiten</h3>
        <div class="media-modal-preview" id="mm-preview"></div>
        <div class="form-group">
            <label for="mm-name">Anzeigename</label>
            <input type="text" id="mm-name">
        </div>
        <div class="form-group">
            <label for="mm-alt">Alt-Text (Bildbeschreibung für Suchmaschinen &amp; Screenreader)</label>
            <input type="text" id="mm-alt" placeholder="z. B. Team bei der Arbeit im Büro">
        </div>
        <div class="form-group">
            <label for="mm-title">Titel (Tooltip)</label>
            <input type="text" id="mm-title">
        </div>
        <div class="form-group">
            <label for="mm-folder">Ordner</label>
            <select id="mm-folder">
                <option value="0">– Kein Ordner –</option>
                <?php foreach ($folders as $folder): ?>
                    <option value="<?= (int) $folder['id'] ?>"><?= e($folder['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" id="mm-cancel">Abbrechen</button>
            <button type="button" class="btn btn-primary" id="mm-save">Speichern</button>
        </div>
    </div>
</div>

<script>
(function () {
    const csrf = <?= json_encode(csrf_token()) ?>;
    const base = <?= json_encode(\Core\App::base()) ?>;
    const grid = document.getElementById('media-grid');
    const emptyHint = document.getElementById('media-empty');
    let activeFolder = 'all';

    /* ---------- Ordner-Filter & Suche ---------- */
    const searchInput = document.getElementById('media-search');
    const folderTools = document.getElementById('media-folder-tools');

    function applyFilter() {
        const q = searchInput.value.trim().toLowerCase();
        let visible = 0;
        grid.querySelectorAll('.media-item').forEach((el) => {
            const matchFolder = activeFolder === 'all' || el.dataset.folder === activeFolder;
            const matchSearch = q === '' || el.dataset.search.includes(q);
            const show = matchFolder && matchSearch;
            el.hidden = !show;
            if (show) visible++;
        });
        emptyHint.hidden = visible > 0;
    }
    searchInput.addEventListener('input', applyFilter);

    document.getElementById('media-folders').addEventListener('click', (e) => {
        const chip = e.target.closest('.media-chip');
        if (!chip) return;
        document.querySelectorAll('.media-chip').forEach((el) => el.classList.toggle('is-active', el === chip));
        activeFolder = chip.dataset.folder;
        document.getElementById('dz-target').textContent = activeFolder !== 'all' ? ' – Ziel: ' + chip.textContent.replace('📁 ', '') : '';
        if (activeFolder !== 'all') {
            folderTools.hidden = false;
            document.getElementById('folder-rename-form').action = base + '/admin/media/folders/' + activeFolder;
            document.getElementById('folder-rename-name').value = chip.textContent.replace('📁 ', '').trim();
            document.getElementById('folder-delete-form').action = base + '/admin/media/folders/' + activeFolder + '/delete';
        } else {
            folderTools.hidden = true;
        }
        applyFilter();
    });

    /* ---------- Bearbeiten-Dialog ---------- */
    const modal = document.getElementById('media-modal');
    let editItem = null;

    grid.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-edit]');
        if (!trigger) return;
        editItem = trigger.closest('.media-item');
        document.getElementById('mm-name').value = editItem.dataset.name;
        document.getElementById('mm-alt').value = editItem.dataset.alt;
        document.getElementById('mm-title').value = editItem.dataset.title;
        document.getElementById('mm-folder').value = editItem.dataset.folder;
        const img = editItem.querySelector('.media-thumb img');
        document.getElementById('mm-preview').innerHTML = img ? '<img src="' + img.src + '" alt="">' : '<span class="media-file-icon">📄</span>';
        modal.hidden = false;
    });
    document.getElementById('mm-cancel').addEventListener('click', () => { modal.hidden = true; });
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.hidden = true; });

    document.getElementById('mm-save').addEventListener('click', () => {
        if (!editItem) return;
        const payload = {
            filename: document.getElementById('mm-name').value.trim(),
            alt: document.getElementById('mm-alt').value.trim(),
            title: document.getElementById('mm-title').value.trim(),
            folder_id: parseInt(document.getElementById('mm-folder').value, 10) || 0,
        };
        fetch(base + '/admin/media/' + editItem.dataset.id + '/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(payload),
        }).then((r) => r.json()).then((res) => {
            if (!res.ok) { window.AdminDialog.alert(res.error || 'Speichern fehlgeschlagen.'); return; }
            editItem.dataset.name = payload.filename || editItem.dataset.name;
            editItem.dataset.alt = payload.alt;
            editItem.dataset.title = payload.title;
            editItem.dataset.folder = String(payload.folder_id);
            editItem.dataset.search = (editItem.dataset.name + ' ' + payload.alt + ' ' + payload.title).toLowerCase();
            const nameEl = editItem.querySelector('.media-name');
            nameEl.textContent = editItem.dataset.name;
            nameEl.title = editItem.dataset.name;
            modal.hidden = true;
            applyFilter();
        }).catch(() => window.AdminDialog.alert('Verbindung fehlgeschlagen.'));
    });

    /* ---------- Upload (Drag & Drop) ---------- */
    const zone = document.getElementById('dropzone');
    const input = document.getElementById('dz-input');
    const message = document.getElementById('dz-message');
    const progress = zone.querySelector('.dz-progress');
    const inner = zone.querySelector('.dz-inner');
    const bar = zone.querySelector('.dz-bar span');
    const barLabel = zone.querySelector('.dz-label');
    let busy = false;

    zone.addEventListener('click', () => { if (!busy) input.click(); });
    zone.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
    input.addEventListener('change', () => { if (input.files.length) upload(input.files); });

    let dragDepth = 0;
    const hasFiles = (e) => e.dataTransfer && Array.from(e.dataTransfer.types || []).includes('Files');
    document.addEventListener('dragenter', (e) => { if (!hasFiles(e)) return; e.preventDefault(); dragDepth++; zone.classList.add('is-drag'); });
    document.addEventListener('dragover', (e) => { if (hasFiles(e)) e.preventDefault(); });
    document.addEventListener('dragleave', (e) => { if (!hasFiles(e)) return; if (--dragDepth <= 0) { dragDepth = 0; zone.classList.remove('is-drag'); } });
    document.addEventListener('drop', (e) => {
        if (!hasFiles(e)) return;
        e.preventDefault();
        dragDepth = 0;
        zone.classList.remove('is-drag');
        if (!busy && e.dataTransfer.files.length) upload(e.dataTransfer.files);
    });

    function upload(files) {
        busy = true;
        const data = new FormData();
        Array.from(files).forEach((f) => data.append('files[]', f));
        if (activeFolder !== 'all') data.append('folder_id', activeFolder);
        inner.hidden = true;
        progress.hidden = false;
        bar.style.width = '0%';
        barLabel.textContent = files.length + ' Datei(en) werden hochgeladen …';
        message.hidden = true;

        const xhr = new XMLHttpRequest();
        xhr.open('POST', base + '/admin/media/upload');
        xhr.setRequestHeader('X-CSRF-Token', csrf);
        xhr.setRequestHeader('X-Requested-With', 'fetch');
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const pct = Math.round((e.loaded / e.total) * 100);
                bar.style.width = pct + '%';
                barLabel.textContent = files.length + ' Datei(en) · ' + pct + ' %' + (pct === 100 ? ' – wird verarbeitet …' : '');
            }
        });
        xhr.addEventListener('load', () => {
            let res = null;
            try { res = JSON.parse(xhr.responseText); } catch (e) {}
            finish(res, xhr.status);
        });
        xhr.addEventListener('error', () => finish(null, 0));
        xhr.send(data);
    }

    function finish(res, status) {
        busy = false;
        input.value = '';
        inner.hidden = false;
        progress.hidden = true;
        if (!res) { note('error', 'Upload fehlgeschlagen (HTTP ' + status + ') – bitte erneut versuchen.'); return; }
        (res.items || []).forEach((item) => grid.prepend(buildItem(item)));
        if (res.uploaded > 0 && (!res.errors || !res.errors.length)) note('success', '✓ ' + res.uploaded + ' Datei(en) hochgeladen.');
        else if (res.uploaded > 0) note('error', res.uploaded + ' hochgeladen – übersprungen: ' + res.errors.join(', '));
        else note('error', res.errors && res.errors.length ? res.errors.join(', ') : 'Nichts hochgeladen.');
        applyFilter();
    }

    function note(type, text) {
        message.className = 'alert alert-' + type;
        message.style.marginTop = '14px';
        message.textContent = text;
        message.hidden = false;
        if (type === 'success') setTimeout(() => { message.hidden = true; }, 5000);
    }

    function buildItem(item) {
        const el = document.createElement('div');
        el.className = 'media-item is-new';
        el.dataset.id = item.id;
        el.dataset.folder = String(item.folderId || 0);
        el.dataset.name = item.name;
        el.dataset.alt = '';
        el.dataset.title = '';
        el.dataset.url = item.url;
        el.dataset.search = item.name.toLowerCase();
        const kb = new Intl.NumberFormat('de-DE').format(Math.round(item.size / 1024));
        el.innerHTML =
            '<div class="media-thumb" data-edit title="Bearbeiten">' +
                (item.isImage ? '<img alt="" loading="lazy">' : '<span class="media-file-icon">📄</span>') +
            '</div>' +
            '<div class="media-name"></div>' +
            '<div class="media-meta muted small"></div>' +
            '<div class="media-actions">' +
                '<button type="button" class="btn btn-small" data-edit>✎</button>' +
                '<button type="button" class="btn btn-small" data-copy="">URL</button>' +
                '<form method="post" class="inline">' +
                    '<input type="hidden" name="_csrf" value="">' +
                    '<button type="submit" class="btn btn-small btn-danger">✕</button>' +
                '</form>' +
            '</div>';
        if (item.isImage) el.querySelector('img').src = item.thumb;
        const name = el.querySelector('.media-name');
        name.textContent = item.name;
        name.title = item.name;
        el.querySelector('.media-meta').textContent = (item.width ? item.width + '×' + item.height + ' · ' : '') + kb + ' KB';
        el.querySelector('[data-copy]').dataset.copy = item.url;
        const form = el.querySelector('form');
        form.action = item.deleteUrl;
        form.querySelector('[name=_csrf]').value = csrf;
        form.setAttribute('data-confirm', 'Datei „' + item.name + '“ wirklich löschen?');
        form.setAttribute('data-confirm-danger', '');
        form.setAttribute('data-confirm-ok', 'Löschen');
        setTimeout(() => el.classList.remove('is-new'), 900);
        return el;
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-copy]');
        if (!btn) return;
        const abs = new URL(btn.dataset.copy, window.location.origin).href;
        navigator.clipboard.writeText(abs).then(() => {
            const old = btn.textContent;
            btn.textContent = '✓';
            setTimeout(() => { btn.textContent = old; }, 1500);
        });
    });
})();
</script>
