<?php
/**
 * Mini-Verwaltung des KI-Dienstes: Lizenzen anlegen, Guthaben aufladen,
 * Verbrauch einsehen. Zugriff nur mit dem Admin-Passwort aus config.php.
 */
declare(strict_types=1);

session_start();

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    exit('config.php fehlt – siehe config.example.php');
}
$config = require $configFile;

require_once __DIR__ . '/index-lib.php';

$loggedIn = ($_SESSION['ai_admin'] ?? false) === true;

if (($_POST['action'] ?? '') === 'login') {
    if (hash_equals((string) ($config['admin_password'] ?? ''), (string) ($_POST['password'] ?? '')) && ($config['admin_password'] ?? '') !== '') {
        $_SESSION['ai_admin'] = true;
        header('Location: admin.php');
        exit;
    }
    $error = 'Falsches Passwort.';
}

if ($loggedIn && ($_POST['action'] ?? '') === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($loggedIn && ($_POST['action'] ?? '') === 'create') {
    $key = 'bw-' . bin2hex(random_bytes(12));
    aiDb()->prepare('INSERT INTO licenses (license_key, name, tokens_total) VALUES (?, ?, ?)')
        ->execute([$key, trim($_POST['name'] ?? '') ?: 'Ohne Namen', max(0, (int) ($_POST['tokens'] ?? 0))]);
    $created = $key;
}

if ($loggedIn && ($_POST['action'] ?? '') === 'topup') {
    aiDb()->prepare('UPDATE licenses SET tokens_total = tokens_total + ? WHERE license_key = ?')
        ->execute([max(0, (int) ($_POST['tokens'] ?? 0)), (string) ($_POST['key'] ?? '')]);
}

if ($loggedIn && ($_POST['action'] ?? '') === 'toggle') {
    aiDb()->prepare('UPDATE licenses SET active = 1 - active WHERE license_key = ?')
        ->execute([(string) ($_POST['key'] ?? '')]);
}

$licenses = $loggedIn ? aiDb()->query('SELECT * FROM licenses ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Blockwerk Orange – KI-Dienst</title>
<style>
* { box-sizing: border-box; }
body { font-family: system-ui, sans-serif; background: #faf6f1; color: #2b1d12; margin: 0; padding: 30px; }
.wrap { max-width: 900px; margin: 0 auto; }
h1 { font-size: 22px; } h1 span { color: #ea580c; }
.card { background: #fff; border: 1px solid #eddfd3; border-radius: 12px; padding: 20px; margin-bottom: 18px; }
input, button { font: inherit; padding: 8px 12px; border: 1px solid #eddfd3; border-radius: 8px; }
button { background: #ea580c; color: #fff; border: none; cursor: pointer; font-weight: 600; }
button.ghost { background: #ece0d3; color: #2b1d12; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eddfd3; }
code { background: #fff1e6; padding: 2px 6px; border-radius: 5px; }
.muted { color: #8d7b6a; font-size: 13px; }
form.inline { display: inline; }
</style>
</head>
<body>
<div class="wrap">
    <h1>Blockwerk <span>Orange</span> – KI-Dienst<?= !empty($config['mock']) ? ' <small class="muted">(Mock-Modus)</small>' : '' ?></h1>

    <?php if (!$loggedIn): ?>
        <div class="card">
            <?php if (!empty($error)): ?><p style="color:#dc2626"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <input type="password" name="password" placeholder="Admin-Passwort" autofocus>
                <button type="submit">Anmelden</button>
            </form>
        </div>
    <?php else: ?>
        <div class="card">
            <h3>Neue Lizenz</h3>
            <?php if (!empty($created)): ?><p>Angelegt: <code><?= htmlspecialchars($created) ?></code></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="create">
                <input type="text" name="name" placeholder="Kunde / Installation">
                <input type="number" name="tokens" placeholder="Start-Guthaben (Tokens)" value="500000">
                <button type="submit">Lizenz anlegen</button>
            </form>
        </div>

        <div class="card">
            <h3>Lizenzen</h3>
            <table>
                <tr><th>Kunde</th><th>Schlüssel</th><th>Guthaben</th><th>Verbraucht</th><th>Status</th><th>Aktion</th></tr>
                <?php foreach ($licenses as $lic): ?>
                    <tr>
                        <td><?= htmlspecialchars($lic['name']) ?></td>
                        <td><code><?= htmlspecialchars($lic['license_key']) ?></code></td>
                        <td><?= number_format(max(0, $lic['tokens_total'] - $lic['tokens_used']), 0, ',', '.') ?></td>
                        <td class="muted"><?= number_format((int) $lic['tokens_used'], 0, ',', '.') ?></td>
                        <td><?= $lic['active'] ? '✓ aktiv' : '✕ gesperrt' ?></td>
                        <td>
                            <form method="post" class="inline">
                                <input type="hidden" name="action" value="topup">
                                <input type="hidden" name="key" value="<?= htmlspecialchars($lic['license_key']) ?>">
                                <input type="number" name="tokens" value="500000" style="width:110px">
                                <button type="submit">+ Aufladen</button>
                            </form>
                            <form method="post" class="inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="key" value="<?= htmlspecialchars($lic['license_key']) ?>">
                                <button type="submit" class="ghost"><?= $lic['active'] ? 'Sperren' : 'Aktivieren' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <form method="post"><input type="hidden" name="action" value="logout"><button type="submit" class="ghost">Abmelden</button></form>
    <?php endif; ?>
</div>
</body>
</html>
