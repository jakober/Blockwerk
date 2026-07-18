<div class="card">
    <div class="dropzone" id="dropzone" tabindex="0" role="button" aria-label="Dateien hochladen">
        <div class="dz-inner">
            <div class="dz-icon">⬆</div>
            <strong>Dateien hierher ziehen</strong>
            <span class="muted">oder klicken zum Auswählen</span>
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
    <p class="muted" id="media-empty" <?= empty($media) ? '' : 'hidden' ?>>Noch keine Dateien in der Mediathek.</p>
    <div class="media-grid" id="media-grid">
        <?php foreach ($media as $item): ?>
            <?php $fileUrl = url('/' . $item['path']); ?>
            <div class="media-item">
                <div class="media-thumb">
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
                    <button type="button" class="btn btn-small" data-copy="<?= e($fileUrl) ?>">URL kopieren</button>
                    <form method="post" action="<?= e(url('/admin/media/' . $item['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('Datei „<?= e($item['filename']) ?>“ wirklich löschen?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-small btn-danger">✕</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function () {
    const zone = document.getElementById('dropzone');
    const input = document.getElementById('dz-input');
    const grid = document.getElementById('media-grid');
    const emptyHint = document.getElementById('media-empty');
    const message = document.getElementById('dz-message');
    const progress = zone.querySelector('.dz-progress');
    const inner = zone.querySelector('.dz-inner');
    const bar = zone.querySelector('.dz-bar span');
    const barLabel = zone.querySelector('.dz-label');
    const csrf = <?= json_encode(csrf_token()) ?>;
    const uploadUrl = <?= json_encode(url('/admin/media/upload')) ?>;
    let busy = false;

    zone.addEventListener('click', () => { if (!busy) input.click(); });
    zone.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
    input.addEventListener('change', () => { if (input.files.length) upload(input.files); });

    // Seitenweites Drag & Drop: Ziehen hebt die Zone hervor, Fallenlassen lädt hoch.
    let dragDepth = 0;
    document.addEventListener('dragenter', (e) => {
        if (!hasFiles(e)) return;
        e.preventDefault();
        dragDepth++;
        zone.classList.add('is-drag');
    });
    document.addEventListener('dragover', (e) => {
        if (hasFiles(e)) e.preventDefault();
    });
    document.addEventListener('dragleave', (e) => {
        if (!hasFiles(e)) return;
        if (--dragDepth <= 0) { dragDepth = 0; zone.classList.remove('is-drag'); }
    });
    document.addEventListener('drop', (e) => {
        if (!hasFiles(e)) return;
        e.preventDefault();
        dragDepth = 0;
        zone.classList.remove('is-drag');
        if (!busy && e.dataTransfer.files.length) upload(e.dataTransfer.files);
    });

    function hasFiles(e) {
        return e.dataTransfer && Array.from(e.dataTransfer.types || []).includes('Files');
    }

    function upload(files) {
        busy = true;
        const data = new FormData();
        Array.from(files).forEach((f) => data.append('files[]', f));
        inner.hidden = true;
        progress.hidden = false;
        bar.style.width = '0%';
        barLabel.textContent = files.length + ' Datei(en) werden hochgeladen …';
        message.hidden = true;

        const xhr = new XMLHttpRequest();
        xhr.open('POST', uploadUrl);
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
        if (!res) {
            note('error', 'Upload fehlgeschlagen (HTTP ' + status + ') – bitte erneut versuchen.');
            return;
        }
        (res.items || []).forEach((item) => grid.prepend(buildItem(item)));
        if ((res.items || []).length) emptyHint.hidden = true;
        if (res.uploaded > 0 && (!res.errors || !res.errors.length)) {
            note('success', '✓ ' + res.uploaded + ' Datei(en) hochgeladen.');
        } else if (res.uploaded > 0) {
            note('error', res.uploaded + ' Datei(en) hochgeladen – übersprungen: ' + res.errors.join(', '));
        } else {
            note('error', res.errors && res.errors.length ? res.errors.join(', ') : 'Nichts hochgeladen.');
        }
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
        const kb = new Intl.NumberFormat('de-DE').format(Math.round(item.size / 1024));
        const meta = (item.width ? item.width + '×' + item.height + ' · ' : '') + kb + ' KB';
        el.innerHTML =
            '<div class="media-thumb">' +
                (item.isImage
                    ? '<img alt="" loading="lazy">'
                    : '<span class="media-file-icon">📄</span>') +
            '</div>' +
            '<div class="media-name"></div>' +
            '<div class="media-meta muted small"></div>' +
            '<div class="media-actions">' +
                '<button type="button" class="btn btn-small" data-copy="">URL kopieren</button>' +
                '<form method="post" class="inline">' +
                    '<input type="hidden" name="_csrf" value="">' +
                    '<button type="submit" class="btn btn-small btn-danger">✕</button>' +
                '</form>' +
            '</div>';
        if (item.isImage) el.querySelector('img').src = item.thumb;
        const name = el.querySelector('.media-name');
        name.textContent = item.name;
        name.title = item.name;
        el.querySelector('.media-meta').textContent = meta;
        el.querySelector('[data-copy]').dataset.copy = item.url;
        const form = el.querySelector('form');
        form.action = item.deleteUrl;
        form.querySelector('[name=_csrf]').value = csrf;
        form.addEventListener('submit', (e) => {
            if (!confirm('Datei „' + item.name + '“ wirklich löschen?')) e.preventDefault();
        });
        setTimeout(() => el.classList.remove('is-new'), 900);
        return el;
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-copy]');
        if (!btn) return;
        const abs = new URL(btn.dataset.copy, window.location.origin).href;
        navigator.clipboard.writeText(abs).then(() => {
            const old = btn.textContent;
            btn.textContent = 'Kopiert ✓';
            setTimeout(() => { btn.textContent = old; }, 1500);
        });
    });
})();
</script>
