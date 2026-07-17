<?php
$isEdit = $page !== null;
$action = $isEdit ? '/admin/pages/' . $page['id'] : '/admin/pages';
?>
<div class="card narrow">
    <form method="post" action="<?= e(url($action)) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="title">Titel</label>
            <input type="text" id="title" name="title" value="<?= e($page['title'] ?? '') ?>" required autofocus>
        </div>
        <div class="form-group">
            <label for="slug">Slug (URL-Pfad, leer = automatisch aus Titel)</label>
            <input type="text" id="slug" name="slug" value="<?= e($page['slug'] ?? '') ?>" placeholder="z. B. ueber-uns">
        </div>
        <div class="form-row">
            <div class="form-group grow">
                <label for="parent_id">Übergeordnete Seite</label>
                <select id="parent_id" name="parent_id">
                    <option value="0">– Keine (oberste Ebene) –</option>
                    <?php foreach ($parents as $parent): ?>
                        <option value="<?= (int) $parent['id'] ?>" <?= (int) ($page['parent_id'] ?? 0) === (int) $parent['id'] ? 'selected' : '' ?>>
                            <?= str_repeat('&nbsp;&nbsp;&nbsp;', (int) $parent['depth']) ?><?= e($parent['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group grow">
                <label for="layout_id">Layout</label>
                <select id="layout_id" name="layout_id">
                    <option value="0">– Standard (erstes Layout) –</option>
                    <?php foreach ($layouts as $layout): ?>
                        <option value="<?= (int) $layout['id'] ?>" <?= (int) ($page['layout_id'] ?? 0) === (int) $layout['id'] ? 'selected' : '' ?>>
                            <?= e($layout['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group checkbox-group grow">
                <label><input type="checkbox" name="in_menu" <?= (int) ($page['in_menu'] ?? 0) ? 'checked' : '' ?>> Im Menü anzeigen</label>
                <label><input type="checkbox" name="published" <?= $isEdit ? ((int) $page['published'] ? 'checked' : '') : 'checked' ?>> Veröffentlicht</label>
            </div>
            <div class="form-group">
                <label for="menu_order">Menü-Reihenfolge</label>
                <input type="number" id="menu_order" name="menu_order" value="<?= (int) ($page['menu_order'] ?? 0) ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Speichern' : 'Seite anlegen' ?></button>
            <a class="btn btn-ghost" href="<?= e(url('/admin/pages')) ?>">Abbrechen</a>
        </div>
    </form>
</div>
