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
                <?php
                    // Bei neuen Seiten das Standard-Layout vorwählen; beim
                    // Bearbeiten das gespeicherte (0 = folgt automatisch dem Standard).
                    $selectedLayout = isset($page) && $page !== null
                        ? (int) ($page['layout_id'] ?? 0)
                        : (int) ($defaultLayoutId ?? 0);
                ?>
                <select id="layout_id" name="layout_id">
                    <option value="0" <?= $selectedLayout === 0 ? 'selected' : '' ?>>– Standard-Layout (automatisch) –</option>
                    <?php foreach ($layouts as $layout): ?>
                        <option value="<?= (int) $layout['id'] ?>" <?= $selectedLayout === (int) $layout['id'] ? 'selected' : '' ?>>
                            <?= e($layout['name']) ?><?= (int) ($layout['is_default'] ?? 0) === 1 ? ' (Standard)' : '' ?>
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
        <?php if (count(cms_langs()) > 1): ?>
            <div class="form-group">
                <label for="lang">Sprache</label>
                <select id="lang" name="lang">
                    <?php foreach (cms_langs() as $langCode): ?>
                        <option value="<?= e($langCode) ?>" <?= ($page['lang'] ?? cms_default_lang()) === $langCode ? 'selected' : '' ?>>
                            <?= e(strtoupper($langCode)) ?><?= $langCode === cms_default_lang() ? ' (Standard)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="muted small">Seiten in anderen Sprachen sind unter /<?= e(implode('/… bzw. /', array_slice(cms_langs(), 1))) ?>/… erreichbar; das Menü zeigt nur Seiten der jeweiligen Sprache. Sprachumschalter: <code>{{languages}}</code> im Layout.</p>
            </div>
        <?php endif; ?>
        <details class="seo-details" <?= !empty($page['meta_title']) || !empty($page['meta_description']) || !empty($page['noindex']) ? 'open' : '' ?>>
            <summary>SEO (Suchmaschinen-Einstellungen)</summary>
            <div class="form-group">
                <label for="meta_title">SEO-Titel (leer = Seitentitel)</label>
                <input type="text" id="meta_title" name="meta_title" maxlength="200" value="<?= e($page['meta_title'] ?? '') ?>" placeholder="Titel für Google &amp; Browser-Tab">
            </div>
            <div class="form-group">
                <label for="meta_description">Meta-Beschreibung (empfohlen: 120–160 Zeichen)</label>
                <textarea id="meta_description" name="meta_description" rows="3" maxlength="500" placeholder="Kurze Beschreibung, die in Suchergebnissen angezeigt wird"><?= e($page['meta_description'] ?? '') ?></textarea>
            </div>
            <div class="form-group checkbox-group">
                <label><input type="checkbox" name="noindex" <?= !empty($page['noindex']) ? 'checked' : '' ?>> Von Suchmaschinen ausschließen (noindex)</label>
            </div>
        </details>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Speichern' : 'Seite anlegen' ?></button>
            <a class="btn btn-ghost" href="<?= e(url('/admin/pages')) ?>">Abbrechen</a>
        </div>
    </form>
</div>
