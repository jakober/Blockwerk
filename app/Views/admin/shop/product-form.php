<?php
$isEdit = $product !== null;
$action = $isEdit ? '/admin/shop/products/' . $product['id'] : '/admin/shop/products';
$priceStr = static fn ($cents) => $cents === null || $cents === '' ? '' : number_format(((int) $cents) / 100, 2, ',', '');
?>
<div class="card">
    <form method="post" action="<?= e(url($action)) ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group grow">
                <label for="name">Produktname</label>
                <input type="text" id="name" name="name" value="<?= e($product['name'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group grow">
                <label for="slug">Slug (leer = automatisch)</label>
                <input type="text" id="slug" name="slug" value="<?= e($product['slug'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group grow">
                <label for="category_id">Kategorie</label>
                <select id="category_id" name="category_id">
                    <option value="0">– keine –</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($product['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= str_repeat('— ', (int) $c['depth']) . e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="sku">Artikelnummer (SKU)</label>
                <input type="text" id="sku" name="sku" value="<?= e($product['sku'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="price">Preis (€)</label>
                <input type="text" id="price" name="price" value="<?= e($priceStr($product['price'] ?? '')) ?>" placeholder="19,90" inputmode="decimal">
            </div>
            <div class="form-group">
                <label for="compare_price">Streichpreis (optional)</label>
                <input type="text" id="compare_price" name="compare_price" value="<?= e($priceStr($product['compare_price'] ?? '')) ?>" placeholder="24,90" inputmode="decimal">
            </div>
            <div class="form-group">
                <label for="stock">Lagerbestand (leer = unbegrenzt)</label>
                <input type="number" id="stock" name="stock" value="<?= e($product['stock'] ?? '') ?>" min="0">
            </div>
            <div class="form-group">
                <label for="weight">Gewicht in g (optional)</label>
                <input type="number" id="weight" name="weight" value="<?= e($product['weight'] ?? '') ?>" min="0">
            </div>
        </div>

        <div class="form-group">
            <label>Produktbild</label>
            <div class="image-field">
                <input type="text" name="image" id="image" value="<?= e($product['image'] ?? '') ?>" placeholder="Bild-URL oder aus der Mediathek wählen">
                <button type="button" class="btn" data-media-pick="#image">Mediathek</button>
            </div>
        </div>

        <div class="form-group">
            <label for="short_desc">Kurzbeschreibung (für Listen/Kacheln)</label>
            <textarea id="short_desc" name="short_desc" rows="2"><?= e($product['short_desc'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="description">Beschreibung</label>
            <textarea id="description" name="description" data-richtext rows="10"><?= e($product['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="position">Sortierung</label>
                <input type="number" id="position" name="position" value="<?= e($product['position'] ?? 0) ?>">
            </div>
            <div class="form-group checkbox-group grow" style="align-self:flex-end">
                <label><input type="checkbox" name="active" <?= $isEdit ? ((int) $product['active'] ? 'checked' : '') : 'checked' ?>> Aktiv (im Shop sichtbar)</label>
                <label><input type="checkbox" name="featured" <?= (int) ($product['featured'] ?? 0) ? 'checked' : '' ?>> Empfohlen (auf Shop-Startseite)</label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Speichern' : 'Anlegen' ?></button>
            <a class="btn btn-ghost" href="<?= e(url('/admin/shop/products')) ?>">Abbrechen</a>
        </div>
    </form>
</div>
<script src="<?= e(asset('/assets/js/admin-tools.js')) ?>"></script>
