<?php
/**
 * Eigenständiger Installations-Bootstrapper.
 *
 * Diese EINE Datei auf den Webspace hochladen und im Browser öffnen:
 *   1. Server-Voraussetzungen werden geprüft.
 *   2. Das CMS-Paket (ZIP) wird heruntergeladen und entpackt.
 *   3. Weiterleitung in den eingebauten Installations-Assistenten
 *      (Datenbank-Zugangsdaten, Website-Name, Admin-Konto).
 *
 * Liegt diese Datei im Repository selbst, wird Schritt 2 übersprungen –
 * sie erkennt eine vorhandene Installation und leitet direkt weiter.
 */
declare(strict_types=1);

session_start();

const DEFAULT_PACKAGE_URL = 'https://github.com/jakober/Cms/archive/refs/heads/main.zip';

$dir = __DIR__;
$alreadyExtracted = is_file($dir . '/public/index.php');
$errors = [];
$step = $_GET['step'] ?? 'check';

/* ---------- Helfer ---------- */

function fetchUrl(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'CMS-Installer',
        ]);
        $data = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return is_string($data) && $status < 400 ? $data : null;
    }
    $context = stream_context_create(['http' => ['timeout' => 120, 'header' => 'User-Agent: CMS-Installer']]);
    $data = @file_get_contents($url, false, $context);
    return $data !== false ? $data : null;
}

/**
 * Entpackt das ZIP und hebt einen evtl. vorhandenen Wurzelordner
 * (z. B. "Cms-main/" bei GitHub-Archiven) eine Ebene nach oben.
 */
function extractPackage(string $zipFile, string $target): ?string
{
    if (!class_exists('ZipArchive')) {
        return 'Die PHP-Erweiterung "zip" fehlt auf diesem Server.';
    }
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        return 'Das heruntergeladene Paket ist kein gültiges ZIP-Archiv.';
    }

    $first = $zip->getNameIndex(0) ?: '';
    $rootFolder = str_contains($first, '/') ? explode('/', $first)[0] . '/' : '';
    // Wurzelordner nur strippen, wenn wirklich ALLE Einträge darin liegen.
    if ($rootFolder !== '') {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (!str_starts_with((string) $zip->getNameIndex($i), $rootFolder)) {
                $rootFolder = '';
                break;
            }
        }
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        $relative = $rootFolder !== '' ? substr($name, strlen($rootFolder)) : $name;
        if ($relative === '' || str_contains($relative, '..')) {
            continue;
        }
        $destination = $target . '/' . $relative;
        if (str_ends_with($name, '/')) {
            if (!is_dir($destination)) {
                mkdir($destination, 0755, true);
            }
            continue;
        }
        $folder = dirname($destination);
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }
        $content = $zip->getFromIndex($i);
        if ($content === false || file_put_contents($destination, $content) === false) {
            $zip->close();
            return 'Die Datei "' . htmlspecialchars($relative) . '" konnte nicht geschrieben werden – bitte Schreibrechte prüfen.';
        }
    }
    $zip->close();
    return null;
}

/* ---------- Schritte ---------- */

$checks = [
    'PHP 8.1 oder neuer' => version_compare(PHP_VERSION, '8.1.0', '>=') ? true : 'Gefunden: PHP ' . PHP_VERSION,
    'PDO-MySQL-Erweiterung' => extension_loaded('pdo_mysql') ?: 'Erweiterung pdo_mysql fehlt',
    'ZIP-Erweiterung' => class_exists('ZipArchive') ?: 'Erweiterung zip fehlt',
    'Downloads möglich (curl oder allow_url_fopen)' => (function_exists('curl_init') || ini_get('allow_url_fopen')) ?: 'Weder curl noch allow_url_fopen verfügbar',
    'Schreibrechte im Verzeichnis' => is_writable($dir) ?: 'Verzeichnis ist nicht beschreibbar',
];
$checksOk = !in_array(false, array_map(static fn ($v) => $v === true, $checks), true)
    && !array_filter($checks, static fn ($v) => $v !== true);

if ($step === 'download' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ($alreadyExtracted) {
        header('Location: ?step=done');
        exit;
    }
    $url = trim($_POST['package_url'] ?? DEFAULT_PACKAGE_URL) ?: DEFAULT_PACKAGE_URL;
    if (!preg_match('~^https://~i', $url)) {
        $errors[] = 'Bitte eine https://-URL zum CMS-Paket (ZIP) angeben.';
    } else {
        $data = fetchUrl($url);
        if ($data === null) {
            $errors[] = 'Das Paket konnte nicht heruntergeladen werden. Ist das Repository öffentlich erreichbar? Alternativ das ZIP manuell entpacken und hochladen.';
        } else {
            $tmp = $dir . '/cms-package.zip';
            if (file_put_contents($tmp, $data) === false) {
                $errors[] = 'Das Paket konnte nicht zwischengespeichert werden (Schreibrechte?).';
            } else {
                $error = extractPackage($tmp, $dir);
                unlink($tmp);
                if ($error !== null) {
                    $errors[] = $error;
                } else {
                    header('Location: ?step=done');
                    exit;
                }
            }
        }
    }
    $step = 'check';
}

if ($step === 'finish') {
    // Bootstrapper entfernen und in den Assistenten des CMS weiterleiten.
    @unlink(__FILE__);
    header('Location: ./');
    exit;
}

$extractedNow = is_file($dir . '/public/index.php');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CMS-Installation</title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    background: linear-gradient(135deg, #0f172a 0%, #312e81 100%); padding: 24px;
    font-size: 15px; line-height: 1.55; color: #0f172a;
}
.card { background: #fff; border-radius: 14px; padding: 36px; width: 100%; max-width: 560px; box-shadow: 0 20px 50px rgba(0,0,0,.3); }
.brand { font-size: 26px; font-weight: 800; letter-spacing: -.5px; margin-bottom: 4px; }
.brand span { font-weight: 400; color: #64748b; font-size: 16px; margin-left: 10px; }
p.lead { color: #475569; margin-top: 6px; }
ul.checks { list-style: none; padding: 0; margin: 18px 0; }
ul.checks li { display: flex; justify-content: space-between; gap: 14px; padding: 9px 2px; border-bottom: 1px dashed #e2e8f0; }
.ok { color: #16a34a; font-weight: 700; }
.fail { color: #dc2626; font-weight: 600; font-size: 13px; text-align: right; }
label { display: block; font-weight: 600; font-size: 13px; margin: 14px 0 6px; }
input[type=text] { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font: inherit; }
input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.15); }
.btn { display: inline-block; width: 100%; text-align: center; margin-top: 18px; padding: 12px 18px; border: none; border-radius: 9px;
    background: #4f46e5; color: #fff; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
.btn:hover { background: #4338ca; }
.btn[disabled] { background: #cbd5e1; cursor: not-allowed; }
.alert { background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 11px 14px; margin: 14px 0; font-size: 14px; }
.success { background: #dcfce7; color: #166534; border-radius: 8px; padding: 11px 14px; margin: 14px 0; }
.muted { color: #64748b; font-size: 13px; }
</style>
</head>
<body>
<div class="card">
    <div class="brand">CMS<span>Installation</span></div>

    <?php foreach ($errors as $error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <?php if ($step === 'done' || $extractedNow): ?>
        <div class="success">Das CMS ist bereit<?= $alreadyExtracted ? '' : ' – das Paket wurde heruntergeladen und entpackt' ?>. ✓</div>
        <p class="lead">Im nächsten Schritt richtest du die Datenbankverbindung, den Namen deiner Website und dein Admin-Konto ein – Schritt für Schritt mit Assistent.</p>
        <a class="btn" href="?step=finish">Weiter zum Einrichtungs-Assistenten →</a>
        <p class="muted">Diese Installationsdatei löscht sich dabei automatisch selbst.</p>
    <?php else: ?>
        <p class="lead">Willkommen! Dieser Assistent lädt das Content-Management-System herunter und richtet es in diesem Verzeichnis ein.</p>

        <ul class="checks">
            <?php foreach ($checks as $label => $result): ?>
                <li>
                    <span><?= htmlspecialchars($label) ?></span>
                    <?php if ($result === true): ?><span class="ok">✓</span>
                    <?php else: ?><span class="fail"><?= htmlspecialchars(is_string($result) ? $result : 'Fehlgeschlagen') ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <form method="post" action="?step=download">
            <label for="package_url">Paket-Quelle (ZIP)</label>
            <input type="text" id="package_url" name="package_url" value="<?= htmlspecialchars(DEFAULT_PACKAGE_URL) ?>">
            <p class="muted">Standard: das offizielle CMS-Paket von GitHub. Nur ändern, wenn du eine eigene Paket-URL nutzt.</p>
            <button type="submit" class="btn" <?= $checksOk ? '' : 'disabled' ?>>CMS herunterladen &amp; entpacken</button>
            <?php if (!$checksOk): ?>
                <p class="muted">Bitte zuerst die rot markierten Server-Voraussetzungen beheben.</p>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
