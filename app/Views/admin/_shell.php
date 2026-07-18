<?php
$nav = [
    'dashboard' => ['Dashboard', '/admin', '◈'],
    'pages' => ['Seiten', '/admin/pages', '▤'],
    'news' => ['News', '/admin/news', '❑'],
    'events' => ['Events', '/admin/events', '◷'],
    'forms' => ['Formulare', '/admin/forms', '✉'],
    'media' => ['Mediathek', '/admin/media', '▧'],
    'globals' => ['Globale Blöcke', '/admin/globals', '∞'],
    'themes' => ['Designs', '/admin/themes', '✦'],
    'layouts' => ['Layouts', '/admin/layouts', '▦'],
    'templates' => ['Templates', '/admin/templates', '⧉'],
    'fonts' => ['Schriften', '/admin/fonts', 'Aa'],
    'users' => ['Benutzer', '/admin/users', '◉'],
    'update' => ['Updates', '/admin/update', '⟳'],
    'settings' => ['Einstellungen', '/admin/settings', '⚙'],
];
// Redakteure sehen nur die Inhalts-Bereiche.
if (!\Core\Auth::isAdmin()) {
    $nav = array_diff_key($nav, array_flip(['themes', 'layouts', 'templates', 'fonts', 'users', 'update', 'settings']));
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Admin') ?> – Blockwerk Orange</title>
<link rel="icon" type="image/svg+xml" href="<?= e(url('/assets/img/logo.svg')) ?>">
<link rel="stylesheet" href="<?= e(url('/assets/css/admin.css')) ?>">
<script>window.CMS_BASE = <?= json_encode(\Core\App::base()) ?>;</script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<div class="admin">
    <aside class="sidebar">
        <div class="sidebar-brand"><?php include APP_PATH . '/Views/_logo.php'; ?><span class="brand-text">Blockwerk<span class="brand-orange">Orange</span></span></div>
        <button type="button" class="admin-burger" aria-label="Menü öffnen" aria-expanded="false"><span></span><span></span><span></span></button>
        <div class="sidebar-drawer">
            <nav class="sidebar-nav">
                <?php foreach ($nav as $key => [$label, $href, $icon]): ?>
                    <a href="<?= e(url($href)) ?>" class="<?= ($active ?? '') === $key ? 'active' : '' ?>">
                        <span class="nav-icon"><?= $icon ?></span><?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer">
                <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Website ansehen ↗</a>
            </div>
        </div>
    </aside>
    <div class="nav-backdrop"></div>
    <div class="main">
        <header class="topbar">
            <h1><?= e($title ?? '') ?></h1>
            <div class="topbar-right">
                <a class="muted" href="<?= e(url('/admin/users/' . (int) ($_SESSION['user_id'] ?? 0) . '/edit')) ?>" title="Profil &amp; Passwort ändern"><?= e($_SESSION['username'] ?? '') ?></a>
                <form method="post" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost">Abmelden</button>
                </form>
            </div>
        </header>
        <?php if (!empty($flash)): ?>
            <div class="flash-wrap"><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div></div>
        <?php endif; ?>
        <div class="content">
            <?= $content ?>
        </div>
    </div>
</div>
<script>
(function () {
    var burger = document.querySelector('.admin-burger');
    var backdrop = document.querySelector('.nav-backdrop');
    if (!burger) { return; }
    function toggle(open) {
        document.body.classList.toggle('nav-open', open);
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    burger.addEventListener('click', function () { toggle(!document.body.classList.contains('nav-open')); });
    if (backdrop) { backdrop.addEventListener('click', function () { toggle(false); }); }
})();
</script>
</body>
</html>
