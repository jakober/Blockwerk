<?php
declare(strict_types=1);

namespace Core;

/**
 * Dateispeicher der KI-Webseite: liest/schreibt die von der KI erzeugten
 * statischen Dateien (HTML/CSS/JS …) unter public/ai-site/ – mit Pfad-Schutz
 * (keine „..") und Endungs-Whitelist. Datenbank-frei; die gesamte „Website"
 * ist der Ordnerinhalt. Der Verlauf (Gedächtnis) liegt in config/.
 */
class AiSite
{
    private const ALLOWED_EXT = ['html', 'htm', 'css', 'js', 'svg', 'json', 'txt', 'xml', 'webmanifest', 'ico'];
    private const JQUERY_URL = 'https://code.jquery.com/jquery-3.7.1.min.js';

    public static function dir(): string
    {
        return BASE_PATH . '/public/ai-site';
    }

    public static function uploadsDir(): string
    {
        return self::dir() . '/uploads';
    }

    public static function historyFile(): string
    {
        return BASE_PATH . '/config/ai-site-history.json';
    }

    /** Öffentliche URL-Basis der KI-Seite (Assets/Bilder). */
    public static function assetBase(): string
    {
        return rtrim(App::base(), '/') . '/ai-site';
    }

    public static function ensureDirs(): void
    {
        foreach ([self::dir(), self::uploadsDir(), self::dir() . '/assets'] as $d) {
            if (!is_dir($d)) {
                @mkdir($d, 0755, true);
            }
        }
    }

    /** Absoluten, abgesicherten Pfad innerhalb von ai-site/ bilden (oder null). */
    public static function safePath(string $rel): ?string
    {
        $rel = ltrim(str_replace('\\', '/', trim($rel)), '/');
        if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0")) {
            return null;
        }
        return self::dir() . '/' . $rel;
    }

    private static function ext(string $rel): string
    {
        return strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    }

    /** Datei schreiben. Rückgabe: null bei Erfolg, sonst Fehlermeldung. */
    public static function writeFile(string $rel, string $content): ?string
    {
        $rel = ltrim(str_replace('\\', '/', trim($rel)), '/');
        if (!in_array(self::ext($rel), self::ALLOWED_EXT, true)) {
            return 'Nicht erlaubte Dateiendung. Erlaubt: ' . implode(', ', self::ALLOWED_EXT);
        }
        $full = self::safePath($rel);
        if ($full === null) {
            return 'Ungültiger Pfad.';
        }
        $sub = dirname($full);
        if (!is_dir($sub) && !@mkdir($sub, 0755, true)) {
            return 'Ordner konnte nicht angelegt werden.';
        }
        if (strlen($content) > 2_000_000) {
            return 'Datei zu groß (max. 2 MB).';
        }
        return @file_put_contents($full, $content) !== false ? null : 'Datei konnte nicht geschrieben werden.';
    }

    public static function readFile(string $rel): ?string
    {
        $full = self::safePath($rel);
        if ($full === null || !is_file($full)) {
            return null;
        }
        $data = @file_get_contents($full);
        return $data === false ? null : $data;
    }

    public static function deleteFile(string $rel): bool
    {
        $full = self::safePath($rel);
        if ($full === null || !is_file($full)) {
            return false;
        }
        return @unlink($full);
    }

    /** Alle Inhaltsdateien (ohne uploads/) als [{path,size}] – aufsteigend. */
    public static function listFiles(): array
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($dir))), '/');
            if (str_starts_with($rel, 'uploads/')) {
                continue;
            }
            $out[] = ['path' => $rel, 'size' => $file->getSize()];
        }
        usort($out, static fn ($a, $b) => strcmp($a['path'], $b['path']));
        return $out;
    }

    /** Verfügbare Bilder (uploads/) als [{url,name}]. */
    public static function listImages(): array
    {
        $dir = self::uploadsDir();
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..' || !is_file($dir . '/' . $f)) {
                continue;
            }
            $out[] = ['name' => $f, 'url' => self::assetBase() . '/uploads/' . rawurlencode($f)];
        }
        return $out;
    }

    /** Bild-Bytes datenbank-frei nach uploads/ speichern, liefert URL zurück. */
    public static function storeImage(string $bytes, string $niceName, string $ext): ?string
    {
        self::ensureDirs();
        $ext = strtolower(preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'png');
        $base = slugify(pathinfo($niceName, PATHINFO_FILENAME)) ?: 'bild';
        $name = $base . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (@file_put_contents(self::uploadsDir() . '/' . $name, $bytes) === false) {
            return null;
        }
        return self::assetBase() . '/uploads/' . rawurlencode($name);
    }

    /* ---------- Gedächtnis (Text-Verlauf) ---------- */

    public static function history(): array
    {
        $data = @file_get_contents(self::historyFile());
        $json = is_string($data) ? json_decode($data, true) : null;
        return is_array($json) ? $json : [];
    }

    public static function addHistory(string $role, string $text): void
    {
        $hist = self::history();
        $hist[] = ['role' => $role, 'text' => mb_substr($text, 0, 8000)];
        if (count($hist) > 60) {
            $hist = array_slice($hist, -60);
        }
        @file_put_contents(self::historyFile(), json_encode($hist, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function clearHistory(): void
    {
        @file_put_contents(self::historyFile(), '[]');
    }

    /* ---------- Erstausstattung ---------- */

    /** Legt Ordner, jQuery und eine Start-Seite an (falls noch nicht vorhanden). */
    public static function scaffold(string $siteName): void
    {
        self::ensureDirs();
        self::ensureJquery();
        if (!is_file(self::dir() . '/index.html')) {
            self::writeFile('index.html', self::starterHtml($siteName));
        }
    }

    /** jQuery einmalig lokal ablegen (self-hosted, kein externes CDN nötig). */
    public static function ensureJquery(): void
    {
        $target = self::dir() . '/assets/jquery.min.js';
        if (is_file($target)) {
            return;
        }
        if (!is_dir(dirname($target))) {
            @mkdir(dirname($target), 0755, true);
        }
        // Bevorzugt die mit dem CMS ausgelieferte lokale Kopie (offline-sicher).
        $bundled = BASE_PATH . '/public/assets/vendor/jquery-3.7.1.min.js';
        if (is_file($bundled)) {
            @copy($bundled, $target);
            return;
        }
        // Fallback: vom CDN laden (falls die Kopie fehlt).
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::JQUERY_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $data = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (is_string($data) && $status === 200 && str_contains($data, 'jQuery')) {
            if (!is_dir(dirname($target))) {
                @mkdir(dirname($target), 0755, true);
            }
            @file_put_contents($target, $data);
        }
    }

    private static function starterHtml(string $siteName): string
    {
        $name = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
        $base = self::assetBase();
        return <<<HTML
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$name}</title>
<style>
  :root { --accent:#ea580c; --ink:#0f172a; --muted:#64748b; --bg:#ffffff; }
  * { box-sizing:border-box; }
  body { margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; color:var(--ink); background:var(--bg); line-height:1.6; }
  .wrap { max-width:820px; margin:0 auto; padding:14vh 24px; text-align:center; }
  h1 { font-size:clamp(2rem,6vw,3.5rem); letter-spacing:-.02em; margin:0 0 .4em; }
  p { color:var(--muted); font-size:1.15rem; max-width:56ch; margin:0 auto; }
  .badge { display:inline-block; background:color-mix(in srgb,var(--accent) 12%,#fff); color:var(--accent); font-weight:700; padding:6px 14px; border-radius:999px; font-size:.85rem; margin-bottom:22px; }
</style>
</head>
<body>
  <main class="wrap">
    <span class="badge">KI-Webseite</span>
    <h1>{$name}</h1>
    <p>Diese Seite ist bereit. Öffne das Backend und beschreibe der KI, welche Website du möchtest – sie erstellt Startseite, Unterseiten und Design für dich.</p>
  </main>
  <script src="{$base}/assets/jquery.min.js"></script>
</body>
</html>
HTML;
    }
}
