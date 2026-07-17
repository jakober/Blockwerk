<?php
$isEdit = $post !== null;
$action = $isEdit ? $basePath . '/' . $post['id'] : $basePath;
$toLocal = static fn (?string $dt): string => $dt ? date('Y-m-d\TH:i', (int) strtotime($dt)) : '';
?>
<div class="card">
    <form method="post" action="<?= e(url($action)) ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group grow">
                <label for="title">Titel</label>
                <input type="text" id="title" name="title" value="<?= e($post['title'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group grow">
                <label for="slug">Slug (leer = automatisch)</label>
                <input type="text" id="slug" name="slug" value="<?= e($post['slug'] ?? '') ?>">
            </div>
        </div>

        <?php if ($type === 'event'): ?>
            <div class="form-row">
                <div class="form-group grow">
                    <label for="start_at">Beginn</label>
                    <input type="datetime-local" id="start_at" name="start_at" value="<?= e($toLocal($post['start_at'] ?? null)) ?>" required>
                </div>
                <div class="form-group grow">
                    <label for="end_at">Ende (optional)</label>
                    <input type="datetime-local" id="end_at" name="end_at" value="<?= e($toLocal($post['end_at'] ?? null)) ?>">
                </div>
                <div class="form-group grow">
                    <label for="location">Ort (optional)</label>
                    <input type="text" id="location" name="location" value="<?= e($post['location'] ?? '') ?>">
                </div>
            </div>
        <?php else: ?>
            <div class="form-group">
                <label for="published_at">Veröffentlichungsdatum (leer = jetzt)</label>
                <input type="datetime-local" id="published_at" name="published_at" value="<?= e($toLocal($post['published_at'] ?? null)) ?>">
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label>Beitragsbild</label>
            <div class="image-field">
                <input type="text" name="image" id="image" value="<?= e($post['image'] ?? '') ?>" placeholder="Bild-URL oder aus der Mediathek wählen">
                <button type="button" class="btn" data-media-pick="#image">Mediathek</button>
            </div>
        </div>

        <div class="form-group">
            <label for="excerpt">Kurzbeschreibung (für Listen/Kacheln)</label>
            <textarea id="excerpt" name="excerpt" rows="2"><?= e($post['excerpt'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="body">Inhalt</label>
            <textarea id="body" name="body" data-richtext rows="12"><?= e($post['body'] ?? '') ?></textarea>
        </div>

        <div class="form-group checkbox-group">
            <label><input type="checkbox" name="published" <?= $isEdit ? ((int) $post['published'] ? 'checked' : '') : 'checked' ?>> Veröffentlicht</label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Speichern' : 'Anlegen' ?></button>
            <a class="btn btn-ghost" href="<?= e(url($basePath)) ?>">Abbrechen</a>
        </div>
    </form>
</div>
<script src="<?= e(url('/assets/js/admin-tools.js')) ?>"></script>
