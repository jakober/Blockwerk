<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation – Schritt 2 – Blockwerk Orange</title>
<link rel="icon" type="image/svg+xml" href="<?= e(url('/assets/img/logo.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/admin.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-card wide">
    <div class="auth-brand"><?php $logoSize = 34; include APP_PATH . '/Views/_logo.php'; ?>Blockwerk <span class="brand-orange">Orange</span><span>Installation</span></div>
    <div class="steps"><span class="step done">1. Datenbank ✓</span><span class="step active">2. Website &amp; Admin</span></div>
    <p class="muted">Fast geschafft! Lege den Namen deiner Website und deinen Admin-Zugang fest.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/install/finish')) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="site_name">Name der Website</label>
            <input type="text" id="site_name" name="site_name" placeholder="z. B. Meine Website" required>
        </div>
        <div class="form-group">
            <label for="username">Admin-Benutzername</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-row">
            <div class="form-group grow">
                <label for="password">Passwort (min. 8 Zeichen)</label>
                <input type="password" id="password" name="password" minlength="8" required>
            </div>
            <div class="form-group grow">
                <label for="password_repeat">Passwort wiederholen</label>
                <input type="password" id="password_repeat" name="password_repeat" minlength="8" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Installation abschließen</button>
    </form>
</div>
</body>
</html>
