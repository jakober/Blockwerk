<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation – Modus wählen – Blockwerk Orange</title>
<link rel="icon" type="image/svg+xml" href="<?= e(url('/assets/img/logo.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/admin.css')) ?>">
<style>
    .mode-cards { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:8px; }
    @media (max-width:640px){ .mode-cards { grid-template-columns:1fr; } }
    .mode-card { display:flex; flex-direction:column; gap:8px; text-align:left; border:1.5px solid var(--border); border-radius:14px; padding:18px; background:#fff; cursor:pointer; }
    .mode-card:hover { border-color:var(--primary); box-shadow:0 6px 22px rgba(234,88,12,.12); }
    .mode-card h3 { margin:2px 0 0; font-size:17px; }
    .mode-card .ico { font-size:26px; }
    .mode-card ul { margin:6px 0 0; padding-left:18px; color:var(--muted); font-size:13px; line-height:1.6; }
    .mode-card button { margin-top:12px; }
</style>
</head>
<body class="auth-body">
<div class="auth-card wide">
    <div class="auth-brand"><?php $logoSize = 34; include APP_PATH . '/Views/_logo.php'; ?>Blockwerk <span class="brand-orange">Orange</span><span>Installation</span></div>
    <p class="muted">Womit möchtest du starten? Du kannst später jederzeit zwischen beiden Modi wechseln, ohne dass etwas verloren geht.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="mode-cards">
        <form method="post" action="<?= e(url('/install/mode')) ?>" class="mode-card">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" value="cms">
            <span class="ico">🧱</span>
            <h3>Content-Management-System</h3>
            <p class="muted small" style="margin:0">Der klassische Baukasten mit Datenbank.</p>
            <ul>
                <li>Seiten, News, Formulare, Shop</li>
                <li>Visueller Block-Editor &amp; Layouts</li>
                <li>Benötigt eine MySQL/MariaDB-Datenbank</li>
            </ul>
            <button type="submit" class="btn btn-primary btn-block">CMS installieren →</button>
        </form>

        <form method="post" action="<?= e(url('/install/mode')) ?>" class="mode-card">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" value="ai">
            <span class="ico">✨</span>
            <h3>KI-Webseite</h3>
            <p class="muted small" style="margin:0">Reine HTML/CSS/jQuery-Seite, von der KI gebaut.</p>
            <ul>
                <li>Nur Lizenzschlüssel + Passwort nötig</li>
                <li><strong>Keine Datenbank</strong></li>
                <li>Du sagst der KI, was sie tun soll – volle Design-Freiheit</li>
            </ul>
            <button type="submit" class="btn btn-primary btn-block">KI-Webseite installieren →</button>
        </form>
    </div>
</div>
</body>
</html>
