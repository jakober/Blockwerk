<div class="card narrow">
    <form method="post" action="<?= e(url('/admin/settings')) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="site_name">Name der Website</label>
            <input type="text" id="site_name" name="site_name" value="<?= e($siteName) ?>" required>
        </div>
        <div class="form-group">
            <label for="home_page">Startseite</label>
            <select id="home_page" name="home_page">
                <option value="0">– Erste veröffentlichte Seite –</option>
                <?php foreach ($pages as $page): ?>
                    <option value="<?= (int) $page['id'] ?>" <?= $homePage === (int) $page['id'] ? 'selected' : '' ?>>
                        <?= str_repeat('&nbsp;&nbsp;&nbsp;', (int) $page['depth']) ?><?= e($page['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Speichern</button>
        </div>
    </form>
</div>
