<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation – Schritt 1</title>
<link rel="icon" type="image/svg+xml" href="<?= e(url('/assets/img/logo.svg')) ?>">
<link rel="stylesheet" href="<?= e(url('/assets/css/admin.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-card wide">
    <div class="auth-brand"><?php $logoSize = 34; include APP_PATH . '/Views/_logo.php'; ?>Blockwerk<span>Installation</span></div>
    <div class="steps"><span class="step active">1. Datenbank</span><span class="step">2. Website &amp; Admin</span></div>
    <p class="muted">Gib die Zugangsdaten deiner MySQL/MariaDB-Datenbank ein. Alle Tabellen werden automatisch angelegt – falls die Datenbank noch nicht existiert, wird versucht, sie zu erstellen.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/install/database')) ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group grow">
                <label for="host">Datenbank-Host</label>
                <input type="text" id="host" name="host" value="localhost" required>
            </div>
            <div class="form-group">
                <label for="port">Port</label>
                <input type="number" id="port" name="port" value="3306" required>
            </div>
        </div>
        <div class="form-group">
            <label for="name">Datenbankname</label>
            <input type="text" id="name" name="name" placeholder="z. B. cms" required>
        </div>
        <div class="form-group">
            <label for="user">Benutzer</label>
            <input type="text" id="user" name="user" required>
        </div>
        <div class="form-group">
            <label for="pass">Passwort</label>
            <input type="password" id="pass" name="pass">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Verbindung testen &amp; Tabellen anlegen</button>
    </form>
</div>
</body>
</html>
