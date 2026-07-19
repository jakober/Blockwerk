<?php
declare(strict_types=1);

namespace Core;

use Models\Layout;
use Models\Setting;

/**
 * Gesamt-Designs ("Themes"): komplette Optik-Pakete aus Layout-HTML,
 * Farbschema und – neu – Design-Tokens (Rundungen, Hero-Höhe, Abstände,
 * Schrift, Button-Form, Schatten). Diese Tokens setzen die CSS-Variablen, die
 * cms-blocks.css überall auswertet – so sieht jedes Design komplett anders aus.
 *
 * Eigene Designs (auch von der KI erstellt) liegen in der Tabelle `themes` und
 * nutzen dieselbe Struktur wie die mitgelieferten.
 */
class Themes
{
    /** Mitgelieferte Designs – bewusst nur wenige, dafür grundverschieden. */
    public static function builtins(): array
    {
        return [
            'blockwerk' => [
                'name' => 'Blockwerk Orange',
                'description' => 'Warmes Orange, dunkelbrauner Kopfbereich, weiche Rundungen – das Hausdesign.',
                'colors' => ['primary' => '#ea580c', 'accent' => '#fbbf24', 'text' => '#2b1d12', 'bg' => '#fffaf5', 'surface' => '#fff1e6'],
                'headerBg' => '#2a1508', 'headerText' => '#ffffff',
                'tokens' => ['header' => 'bar', 'radius' => 12, 'button' => 'round', 'hero' => 68, 'container' => 1120, 'section' => 56, 'shadow' => 'soft', 'scale' => 16.5, 'headingWeight' => 800, 'headingSpacing' => '-.4px', 'uppercase' => false, 'headingFont' => 'sans'],
            ],
            'kontrast' => [
                'name' => 'Kontrast — groß & mutig',
                'description' => 'Riesiger Vollbild-Hero (100 vh), scharfe Kanten, große Versal-Überschriften – plakativ und modern.',
                'colors' => ['primary' => '#111827', 'accent' => '#ef4444', 'text' => '#0b0f19', 'bg' => '#ffffff', 'surface' => '#f3f4f6'],
                'headerBg' => '#0b0f19', 'headerText' => '#ffffff',
                'tokens' => ['header' => 'bar', 'radius' => 0, 'button' => 'sharp', 'hero' => 100, 'container' => 1280, 'section' => 96, 'shadow' => 'none', 'scale' => 18, 'headingWeight' => 800, 'headingSpacing' => '-1.2px', 'uppercase' => true, 'headingFont' => 'sans'],
            ],
            'atelier' => [
                'name' => 'Atelier — weich & rund',
                'description' => 'Sehr runde Formen, Pillen-Buttons, sanfte Verläufe und großzügige Schatten – freundlich und verspielt.',
                'colors' => ['primary' => '#7c3aed', 'accent' => '#ec4899', 'text' => '#3b3054', 'bg' => '#faf7ff', 'surface' => '#f1e9ff'],
                'headerBg' => 'linear-gradient(120deg, #7c3aed, #ec4899)', 'headerText' => '#ffffff',
                'tokens' => ['header' => 'bar', 'radius' => 26, 'button' => 'pill', 'hero' => 74, 'container' => 1080, 'section' => 70, 'shadow' => 'strong', 'scale' => 16.5, 'headingWeight' => 800, 'headingSpacing' => '-.4px', 'uppercase' => false, 'headingFont' => 'sans'],
            ],
            'journal' => [
                'name' => 'Journal — Magazin',
                'description' => 'Serifen-Überschriften, schmale Lesebreite, zentrierter Kopf und feine Linien – redaktionell und edel.',
                'colors' => ['primary' => '#1c1917', 'accent' => '#b45309', 'text' => '#292524', 'bg' => '#fbf9f4', 'surface' => '#f0eadd'],
                'headerBg' => '#fbf9f4', 'headerText' => '#1c1917',
                'tokens' => ['header' => 'center', 'radius' => 3, 'button' => 'sharp', 'hero' => 58, 'container' => 900, 'section' => 46, 'shadow' => 'none', 'scale' => 18, 'headingWeight' => 700, 'headingSpacing' => '0', 'uppercase' => false, 'headingFont' => 'serif'],
            ],
        ];
    }

    /** Eigene Designs aus der Datenbank (inkl. KI-erstellter). */
    public static function custom(): array
    {
        $out = [];
        try {
            $rows = Database::pdo()->query('SELECT * FROM themes ORDER BY created_at DESC')->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        foreach ($rows as $row) {
            $cfg = json_decode((string) $row['config'], true);
            if (!is_array($cfg)) {
                continue;
            }
            $cfg['name'] = $row['name'];
            $cfg['description'] = $row['description'] ?? '';
            $cfg['custom'] = true;
            $out[(string) $row['tkey']] = $cfg;
        }
        return $out;
    }

    public static function all(): array
    {
        return self::custom() + self::builtins();
    }

    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function activeKey(): string
    {
        return Setting::get('active_theme', 'blockwerk');
    }

    /** Aktiviert ein Design: überschreibt das Standard-Layout. */
    public static function apply(string $key): bool
    {
        $theme = self::find($key);
        if ($theme === null) {
            return false;
        }

        $design = json_encode([
            'colors' => $theme['colors'],
            'fonts' => [],
            'css' => self::css($theme),
        ], JSON_UNESCAPED_UNICODE) ?: null;

        $layout = Layout::default();
        if ($layout === null) {
            $id = Layout::create('Standard (' . $theme['name'] . ')', self::html($theme), $design);
        } else {
            $id = (int) $layout['id'];
            Layout::update($id, 'Standard (' . $theme['name'] . ')', self::html($theme), $design);
        }
        Layout::saveBuilder($id, null);
        Setting::set('active_theme', $key);
        return true;
    }

    /* ---------- Eigene Designs verwalten ---------- */

    /** Eigenes Design speichern (neu oder überschreiben). Liefert den Key. */
    public static function saveCustom(string $name, string $description, array $colors, string $headerBg, string $headerText, array $tokens): string
    {
        $key = self::uniqueKey($name);
        $config = json_encode([
            'colors' => $colors,
            'headerBg' => $headerBg,
            'headerText' => $headerText,
            'tokens' => $tokens,
        ], JSON_UNESCAPED_UNICODE);
        Database::pdo()
            ->prepare('INSERT INTO themes (tkey, name, description, config) VALUES (?, ?, ?, ?)')
            ->execute([$key, $name, $description, $config]);
        return $key;
    }

    public static function deleteCustom(string $key): void
    {
        Database::pdo()->prepare('DELETE FROM themes WHERE tkey = ?')->execute([$key]);
    }

    public static function isCustom(string $key): bool
    {
        return !isset(self::builtins()[$key]) && isset(self::all()[$key]);
    }

    private static function uniqueKey(string $name): string
    {
        $base = 'my-' . (slugify($name) ?: 'design');
        $key = $base;
        $i = 2;
        $existing = array_keys(self::all());
        while (in_array($key, $existing, true)) {
            $key = $base . '-' . $i++;
        }
        return $key;
    }

    /* ---------- Layout-HTML ---------- */

    private static function html(array $theme): string
    {
        $center = (($theme['tokens']['header'] ?? 'bar') === 'center');
        $header = $center
            ? <<<'HTML'
<header class="t-header">
  <div class="container t-brandwrap"><a class="t-brand" href="{{base_url}}/">{{site_name}}</a></div>
  <nav class="t-nav"><div class="container">{{menu}}</div></nav>
</header>
HTML
            : <<<'HTML'
<header class="t-header">
  <div class="container t-headerbar">
    <a class="t-brand" href="{{base_url}}/">{{site_name}}</a>
    <nav class="t-nav">{{menu}}</nav>
  </div>
</header>
HTML;

        return <<<HTML
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{title}} – {{site_name}}</title>
</head>
<body class="bw-theme">
$header
<main class="container t-main">
{{content}}
</main>
<footer class="t-footer">
  <div class="container">&copy; {{year}} {{site_name}}</div>
</footer>
</body>
</html>
HTML;
    }

    /* ---------- CSS: Tokens + Kopf/Fuß + Feinschliff ---------- */

    private static function css(array $theme): string
    {
        $t = $theme['tokens'] ?? [];
        $headerBg = $theme['headerBg'] ?? '#ffffff';
        $headerText = $theme['headerText'] ?? '#111111';
        $center = (($t['header'] ?? 'bar') === 'center');
        $serif = (($t['headingFont'] ?? 'sans') === 'serif');

        $tokens = self::tokenRootCss($t);

        $base = <<<CSS
*{box-sizing:border-box}html,body{margin:0;padding:0}
body{line-height:1.65}
.container{max-width:var(--cms-container,1100px);margin:0 auto;padding:0 20px}
a{color:var(--cms-primary)}
img{max-width:100%;height:auto}
.t-header{background:$headerBg;color:$headerText}
.t-headerbar{display:flex;align-items:center;justify-content:space-between;gap:24px;min-height:70px;flex-wrap:wrap}
.t-brand{font-size:21px;font-weight:800;letter-spacing:-.4px;color:inherit;text-decoration:none}
.t-nav ul.menu{display:flex;gap:4px;list-style:none;margin:0;padding:0;flex-wrap:wrap}
.t-nav li{position:relative}
.t-nav a{display:block;padding:8px 14px;color:inherit;text-decoration:none;font-weight:600;border-radius:8px}
.t-nav a:hover{background:color-mix(in srgb,$headerText 16%,transparent)}
.t-nav ul.submenu{display:none;position:absolute;top:100%;left:0;min-width:200px;background:var(--cms-bg);border:1px solid color-mix(in srgb,var(--cms-text) 15%,transparent);border-radius:var(--cms-radius,10px);box-shadow:var(--cms-shadow);list-style:none;margin:0;padding:6px;z-index:60;color:var(--cms-text)}
.t-nav li.has-children:hover>ul.submenu,.t-nav li.has-children:focus-within>ul.submenu{display:block}
.t-nav ul.submenu ul.submenu{top:0;left:100%}
.t-main{padding-top:40px;padding-bottom:56px}
.t-footer{padding:30px 0;font-size:14px;color:color-mix(in srgb,var(--cms-text) 65%,transparent);border-top:1px solid color-mix(in srgb,var(--cms-text) 12%,transparent);margin-top:40px}
.cms-btn-primary:hover{filter:brightness(.94)}
CSS;

        $extra = '';
        if ($center) {
            $extra .= ".t-brandwrap{text-align:center;padding:26px 20px 14px}.t-brand{font-size:34px}"
                . ".t-nav{border-top:1px solid color-mix(in srgb,var(--cms-text) 15%,transparent);border-bottom:1px solid color-mix(in srgb,var(--cms-text) 15%,transparent)}"
                . ".t-nav ul.menu{justify-content:center}.t-nav a{text-transform:uppercase;font-size:13px;letter-spacing:1.4px}\n";
        }
        if ($serif) {
            $extra .= ".t-brand{font-family:Georgia,'Times New Roman',serif}\n";
        }
        // Sticky-Kopf bei dunklem Kopfbereich wirkt gut; bei hellem dezenter.
        $extra .= ".t-header{position:sticky;top:0;z-index:50}\n";

        return $tokens . "\n" . $base . "\n" . $extra;
    }

    /** :root mit allen Design-Tokens. */
    private static function tokenRootCss(array $t): string
    {
        $radius = (int) ($t['radius'] ?? 12);
        $btn = match ($t['button'] ?? 'round') {
            'pill' => '999px',
            'sharp' => '0',
            default => $radius . 'px',
        };
        $hero = max(30, min(100, (int) ($t['hero'] ?? 65)));
        $container = max(700, min(1600, (int) ($t['container'] ?? 1100)));
        $section = max(0, min(200, (int) ($t['section'] ?? 0)));
        $shadow = match ($t['shadow'] ?? 'soft') {
            'none' => 'none',
            'strong' => '0 26px 64px rgba(0,0,0,.16)',
            default => '0 12px 34px rgba(0,0,0,.08)',
        };
        $scale = (float) ($t['scale'] ?? 16);
        $scale = max(14, min(22, $scale));
        $hw = (int) ($t['headingWeight'] ?? 800);
        $hs = self::cssLen((string) ($t['headingSpacing'] ?? '-.3px'));
        $ht = !empty($t['uppercase']) ? 'uppercase' : 'none';
        $hf = match ($t['headingFont'] ?? 'sans') {
            'serif' => "Georgia,'Times New Roman',serif",
            'mono' => 'ui-monospace,SFMono-Regular,Menlo,monospace',
            default => 'inherit',
        };

        return ':root{'
            . '--cms-radius:' . $radius . 'px;'
            . '--cms-btn-radius:' . $btn . ';'
            . '--cms-hero-h:' . $hero . 'vh;'
            . '--cms-container:' . $container . 'px;'
            . '--cms-section-space:' . $section . 'px;'
            . '--cms-shadow:' . $shadow . ';'
            . '--cms-font-scale:' . rtrim(rtrim(number_format($scale, 2, '.', ''), '0'), '.') . 'px;'
            . '--cms-heading-weight:' . $hw . ';'
            . '--cms-heading-spacing:' . $hs . ';'
            . '--cms-heading-transform:' . $ht . ';'
            . '--cms-heading-font:' . $hf . ';'
            . '}';
    }

    /** Nur unbedenkliche Längenangaben zulassen (z. B. „-1.2px", „0"). */
    private static function cssLen(string $value): string
    {
        return preg_match('/^-?\d+(\.\d+)?(px|em|rem)?$/', trim($value)) ? trim($value) : '-.3px';
    }
}
