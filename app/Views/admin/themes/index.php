<div class="card" style="margin-bottom:20px">
    <p class="muted" style="margin:0">Ein Design ändert die <strong>komplette Optik</strong> deiner Website – Größe, Rundungen, Hero-Höhe, Abstände, Schriftstil, Button-Form und Kopfbereich. <strong>Deine Inhalte bleiben dabei unverändert</strong> und passen sich automatisch an. Beim Aktivieren wird das Standard-Layout überschrieben (auch eigene Farben im Layout-Designer). Tipp: Der <a href="<?= e(url('/admin/ai')) ?>">KI-Assistent</a> kann dir ein <strong>individuelles Design nach Beschreibung</strong> erstellen – es erscheint danach hier.</p>
</div>

<div class="card brand-download" style="margin-bottom:20px">
    <h2>Logo &amp; Markenzeichen</h2>
    <p class="muted small">Das Blockwerk-Orange-Logo als SVG-Datei (verlustfrei skalierbar) zum Herunterladen.</p>
    <div class="brand-grid">
        <div class="brand-item">
            <div class="brand-preview"><img src="<?= e(asset('/assets/img/blockwerk-orange-logo.svg')) ?>" alt="Blockwerk Orange Logo mit Schriftzug" height="52"></div>
            <a class="btn btn-small" href="<?= e(url('/assets/img/blockwerk-orange-logo.svg')) ?>" download>Logo mit Schriftzug (SVG)</a>
        </div>
        <div class="brand-item">
            <div class="brand-preview"><img src="<?= e(asset('/assets/img/logo.svg')) ?>" alt="Blockwerk Orange Bildmarke" height="52"></div>
            <a class="btn btn-small" href="<?= e(url('/assets/img/logo.svg')) ?>" download>Nur Bildmarke (SVG)</a>
        </div>
    </div>
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
                    <?php if (!empty($theme['custom'])): ?><span class="badge badge-orange">Eigenes</span><?php endif; ?>
                </div>
                <p class="muted small"><?= e($theme['description']) ?></p>
                <?php if ($key !== $activeKey): ?>
                    <form method="post" action="<?= e(url('/admin/themes/' . $key . '/apply')) ?>" data-confirm="Design „<?= e($theme['name']) ?>“ aktivieren? Das Standard-Layout wird dabei ersetzt." data-confirm-ok="Aktivieren">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary btn-block">Aktivieren</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-ghost btn-block" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Website ansehen ↗</a>
                <?php endif; ?>
                <?php if (!empty($theme['custom'])): ?>
                    <form method="post" action="<?= e(url('/admin/themes/' . $key . '/delete')) ?>" data-confirm="Eigenes Design „<?= e($theme['name']) ?>“ löschen?" data-confirm-danger data-confirm-ok="Löschen" style="margin-top:6px">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-ghost btn-small btn-block">Löschen</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
