<div class="card" style="margin-bottom:20px">
    <p class="muted" style="margin:0">Ein Design ändert die komplette Optik deiner Website – Kopfbereich, Menü, Farben, Formen und Schriftstil. <strong>Deine Inhalte bleiben dabei unverändert</strong> und passen sich automatisch an. Beim Aktivieren wird das Standard-Layout überschrieben; eigene Änderungen daran (auch eigene Farben im Layout-Designer) werden ersetzt.</p>
</div>

<div class="theme-grid">
    <?php foreach ($themes as $key => $theme): ?>
        <?php $c = $theme['colors']; ?>
        <div class="theme-card <?= $key === $activeKey ? 'is-active' : '' ?>">
            <div class="theme-preview" style="background:<?= e($c['bg']) ?>">
                <div class="tp-header" style="background:<?= e($theme['headerBg']) ?>">
                    <span class="tp-brand" style="background:<?= e($theme['headerText']) ?>"></span>
                    <span class="tp-nav">
                        <i style="background:<?= e($theme['headerText']) ?>"></i>
                        <i style="background:<?= e($theme['headerText']) ?>"></i>
                        <i style="background:<?= e($c['primary']) ?>"></i>
                    </span>
                </div>
                <div class="tp-hero" style="background:<?= e($c['primary']) ?>">
                    <i style="background:<?= e($c['bg']) ?>"></i>
                    <b style="background:<?= e($c['accent']) ?>"></b>
                </div>
                <div class="tp-cols">
                    <span style="background:<?= e($c['surface']) ?>"><i style="background:<?= e($c['text']) ?>"></i><i style="background:<?= e($c['text']) ?>"></i></span>
                    <span style="background:<?= e($c['surface']) ?>"><i style="background:<?= e($c['text']) ?>"></i><i style="background:<?= e($c['accent']) ?>"></i></span>
                    <span style="background:<?= e($c['surface']) ?>"><i style="background:<?= e($c['text']) ?>"></i><i style="background:<?= e($c['text']) ?>"></i></span>
                </div>
            </div>
            <div class="theme-body">
                <div class="theme-name">
                    <?= e($theme['name']) ?>
                    <?php if ($key === $activeKey): ?><span class="badge badge-green">Aktiv</span><?php endif; ?>
                </div>
                <p class="muted small"><?= e($theme['description']) ?></p>
                <?php if ($key !== $activeKey): ?>
                    <form method="post" action="<?= e(url('/admin/themes/' . $key . '/apply')) ?>" onsubmit="return confirm('Design „<?= e($theme['name']) ?>“ aktivieren? Das Standard-Layout wird dabei ersetzt.')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary btn-block">Aktivieren</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-ghost btn-block" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Website ansehen ↗</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
