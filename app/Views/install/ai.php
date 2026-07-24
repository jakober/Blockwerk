<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation – KI-Webseite – Blockwerk Orange</title>
<link rel="icon" type="image/svg+xml" href="<?= e(url('/assets/img/logo.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/admin.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-card wide">
    <div class="auth-brand"><?php $logoSize = 34; include APP_PATH . '/Views/_logo.php'; ?>Blockwerk <span class="brand-orange">Orange</span><span>KI-Webseite</span></div>
    <p class="muted">Für die KI-Webseite brauchst du <strong>keine Datenbank</strong> – nur deinen Lizenzschlüssel (beginnt mit <code>bw-</code>) und ein Passwort fürs Backend.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/install/ai/finish')) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="license">Lizenzschlüssel</label>
            <input type="text" id="license" name="license" placeholder="bw-…" required>
        </div>
        <div class="form-group">
            <label for="site_name">Name der Website</label>
            <input type="text" id="site_name" name="site_name" placeholder="z. B. Café Sonnenschein" required>
        </div>
        <div class="form-group">
            <label for="username">Admin-Benutzername</label>
            <input type="text" id="username" name="username" value="admin" required>
        </div>
        <div class="form-row">
            <div class="form-group grow">
                <label for="password">Passwort (mind. 8 Zeichen)</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group grow">
                <label for="password_repeat">Passwort wiederholen</label>
                <input type="password" id="password_repeat" name="password_repeat" required>
            </div>
        </div>
        <details style="margin-bottom:12px">
            <summary class="muted small" style="cursor:pointer">Erweitert: eigene KI-Dienst-URL</summary>
            <div class="form-group" style="margin-top:8px">
                <label for="service_url">Dienst-URL (leer = Standard)</label>
                <input type="text" id="service_url" name="service_url" placeholder="https://…/ai-server">
            </div>
        </details>
        <button type="submit" class="btn btn-primary btn-block">KI-Webseite installieren</button>
    </form>
    <p style="margin-top:14px"><a class="muted small" href="<?= e(url('/install')) ?>">← Zurück zur Modus-Auswahl</a></p>
</div>
</body>
</html>
