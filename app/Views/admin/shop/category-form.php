<?php
$isEdit = $category !== null;
$action = $isEdit ? '/admin/shop/categories/' . $category['id'] : '/admin/shop/categories';
?>
<div class="card">
    <form method="post" action="<?= e(url($action)) ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group grow">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?= e($category['name'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group grow">
                <label for="slug">Slug (leer = automatisch)</label>
                <input type="text" id="slug" name="slug" value="<?= e($category['slug'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group grow">
                <label for="parent_id">Übergeordnete Kategorie</label>
                <select id="parent_id" name="parent_id">
                    <option value="0">– keine (Hauptkategorie) –</option>
                    <?php foreach ($parents as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($category['parent_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= str_repeat('— ', (int) $c['depth']) . e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="position">Sortierung</label>
                <input type="number" id="position" name="position" value="<?= e($category['position'] ?? 0) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Kategoriebild (optional)</label>
            <div class="image-field">
                <input type="text" name="image" id="image" value="<?= e($category['image'] ?? '') ?>" placeholder="Bild-URL oder aus der Mediathek wählen">
                <button type="button" class="btn" data-media-pick="#image">Mediathek</button>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Beschreibung (optional, oben auf der Kategorieseite)</label>
            <textarea id="description" name="description" rows="3"><?= e($category['description'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Speichern' : 'Anlegen' ?></button>
            <a class="btn btn-ghost" href="<?= e(url('/admin/shop/categories')) ?>">Abbrechen</a>
        </div>
    </form>
</div>
<script src="<?= e(asset('/assets/js/admin-tools.js')) ?>"></script>
