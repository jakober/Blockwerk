<?php /** Minimale, datenbankfreie Backend-Hülle für den KI-Webseiten-Modus. */ ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'KI-Webseite') ?> – Blockwerk Orange</title>
<link rel="icon" type="image/svg+xml" href="<?= e(url('/assets/img/logo.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/admin.css')) ?>">
<script>window.CMS_BASE = <?= json_encode(\Core\App::base()) ?>; window.CSRF = <?= json_encode(csrf_token()) ?>;</script>
<style>
    .aiw-top { display:flex; align-items:center; gap:12px; padding:12px 20px; background:var(--surface); border-bottom:1px solid var(--border); }
    .aiw-top .brand { display:flex; align-items:center; gap:10px; font-weight:800; }
    .aiw-top .brand-orange { color:var(--primary); }
    .aiw-top .spacer { margin-left:auto; }
    .aiw-wrap { max-width:900px; margin:0 auto; padding:22px 20px 60px; }
</style>
</head>
<body class="ai-mode">
<div class="aiw-top">
    <span class="brand"><?php $logoSize = 26; include APP_PATH . '/Views/_logo.php'; ?>Blockwerk <span class="brand-orange">Orange</span></span>
    <span class="badge badge-amber">KI-Webseite</span>
    <span class="spacer"></span>
    <a class="btn btn-ghost btn-small" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Website ansehen ↗</a>
    <form method="post" action="<?= e(url('/logout')) ?>" class="inline"><?= csrf_field() ?><button type="submit" class="btn btn-ghost btn-small">Abmelden</button></form>
</div>
<div class="aiw-wrap">
    <?php if (!empty($flash['message'])): ?>
        <div class="alert alert-<?= e($flash['type'] === 'error' ? 'error' : 'success') ?>"><?= nl2br(e($flash['message'])) ?></div>
    <?php endif; ?>
    <?= $content ?>
</div>
</body>
</html>
