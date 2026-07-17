<div class="card">
    <h2>Dateien hochladen</h2>
    <form method="post" action="<?= e(url('/admin/media/upload')) ?>" enctype="multipart/form-data" class="upload-form">
        <?= csrf_field() ?>
        <input type="file" name="files[]" multiple accept="image/*,.pdf" required>
        <button type="submit" class="btn btn-primary">Hochladen</button>
        <span class="muted small">Erlaubt: JPG, PNG, GIF, WebP, SVG, PDF · max. <?= e((string) $maxUpload) ?> pro Datei</span>
    </form>
</div>

<div class="card">
    <?php if (empty($media)): ?>
        <p class="muted">Noch keine Dateien in der Mediathek.</p>
    <?php else: ?>
        <div class="media-grid">
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
    <?php endif; ?>
</div>

<script>
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    const abs = new URL(btn.dataset.copy, window.location.origin).href;
    navigator.clipboard.writeText(abs).then(() => {
        const old = btn.textContent;
        btn.textContent = 'Kopiert ✓';
        setTimeout(() => { btn.textContent = old; }, 1500);
    });
});
</script>
