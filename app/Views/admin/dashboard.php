<?php
$stats = [
    ['/admin/pages', $counts['pages'], 'Seiten'],
    ['/admin/news', $counts['news'], 'News'],
    ['/admin/events', $counts['events'], 'Events'],
    ['/admin/media', $counts['media'], 'Medien'],
];
if (\Core\Auth::isAdmin()) {
    $stats[] = ['/admin/layouts', $counts['layouts'], 'Layouts'];
    $stats[] = ['/admin/templates', $counts['templates'], 'Templates'];
}
if (isset($counts['shop_products'])) {
    $stats[] = ['/admin/shop/products', $counts['shop_products'], 'Produkte'];
    $stats[] = ['/admin/shop/orders', $counts['shop_orders'], 'Bestellungen'];
}
?>

<?php if (!empty($updateVersion)): ?>
    <a class="card dash-update" href="<?= e(url('/admin/update')) ?>">
        <span class="dash-update-icon">⟳</span>
        <span class="dash-update-text">
            <strong>Update verfügbar: Version <?= e($updateVersion) ?></strong>
            <span class="muted small">Installiert ist Version <?= e($currentVersion) ?>. Jetzt aktualisieren.</span>
        </span>
        <span class="btn btn-primary btn-small dash-update-btn">Zu den Updates →</span>
    </a>
<?php endif; ?>

<div class="cards">
    <?php foreach ($stats as [$href, $count, $label]): ?>
        <a class="card stat" href="<?= e(url($href)) ?>">
            <div class="stat-value"><?= (int) $count ?></div>
            <div class="stat-label"><?= e($label) ?></div>
        </a>
    <?php endforeach; ?>
</div>

<div class="dash-cols">
    <div class="card">
        <h2>Schnellzugriff</h2>
        <div class="dash-actions">
            <?php if (\Core\Auth::isAdmin()): ?>
                <a class="btn btn-primary" href="<?= e(url('/admin/ai')) ?>">✨ KI-Assistent</a>
            <?php endif; ?>
            <a class="btn" href="<?= e(url('/admin/pages/new')) ?>">+ Neue Seite</a>
            <a class="btn btn-ghost" href="<?= e(url('/admin/media')) ?>">Mediathek</a>
            <a class="btn btn-ghost" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Website ansehen ↗</a>
        </div>
        <p class="muted small">Tipp: Beschreibe dem KI-Assistenten einfach, was du brauchst – er plant es und setzt es Schritt für Schritt um.</p>
    </div>

    <div class="card">
        <h2>System</h2>
        <p><strong>Blockwerk Orange</strong> · Version <?= e($currentVersion) ?></p>
        <?php if (\Core\Auth::isAdmin()): ?>
            <?php if (!empty($updateVersion)): ?>
                <p><span class="badge badge-amber">Update auf <?= e($updateVersion) ?> verfügbar</span>
                    <a href="<?= e(url('/admin/update')) ?>">Jetzt aktualisieren →</a></p>
            <?php else: ?>
                <p><span class="badge badge-green">✓ Auf dem neuesten Stand</span></p>
            <?php endif; ?>
            <p class="muted small">PHP <?= e(PHP_VERSION) ?> · vor jedem Update ein <a href="<?= e(url('/admin/update')) ?>">Backup</a> herunterladen.</p>
        <?php endif; ?>
    </div>
</div>
