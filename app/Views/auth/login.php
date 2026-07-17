<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Anmelden</title>
<link rel="stylesheet" href="<?= e(url('/assets/css/admin.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-card">
    <div class="auth-brand">Blockwerk<span>Anmeldung</span></div>

    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/login')) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="username">Benutzername</label>
            <input type="text" id="username" name="username" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Passwort</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Anmelden</button>
    </form>
</div>
</body>
</html>
