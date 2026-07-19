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
                'tokens' => ['header' => 'bar', 'radius' => 12, 'button' => 'round', 'hero' => 68, 'container' => 1120, 'section' => 56, 'shadow' => 'soft', 'scale' => 16.5, 'headingWeight' => 800, 'headingSpacing' => '-.4px', 'uppercase' => false, 'headingFont' => 'sans', 'pack' => 'panel'],
            ],
            'kontrast' => [
                'name' => 'Kontrast — groß & mutig',
                'description' => 'Riesiger Vollbild-Hero (100 vh), scharfe Kanten, große Versal-Überschriften – plakativ und modern.',
                'colors' => ['primary' => '#111827', 'accent' => '#ef4444', 'text' => '#0b0f19', 'bg' => '#ffffff', 'surface' => '#f3f4f6'],
                'headerBg' => '#0b0f19', 'headerText' => '#ffffff',
                'tokens' => ['header' => 'bar', 'radius' => 0, 'button' => 'sharp', 'hero' => 100, 'container' => 1280, 'section' => 96, 'shadow' => 'none', 'scale' => 18, 'headingWeight' => 800, 'headingSpacing' => '-1.2px', 'uppercase' => true, 'headingFont' => 'sans', 'pack' => 'bold'],
            ],
            'atelier' => [
                'name' => 'Atelier — weich & rund',
                'description' => 'Sehr runde Formen, Pillen-Buttons, sanfte Verläufe und großzügige Schatten – freundlich und verspielt.',
                'colors' => ['primary' => '#7c3aed', 'accent' => '#ec4899', 'text' => '#3b3054', 'bg' => '#faf7ff', 'surface' => '#f1e9ff'],
                'headerBg' => 'linear-gradient(120deg, #7c3aed, #ec4899)', 'headerText' => '#ffffff',
                'tokens' => ['header' => 'bar', 'radius' => 26, 'button' => 'pill', 'hero' => 74, 'container' => 1080, 'section' => 70, 'shadow' => 'strong', 'scale' => 16.5, 'headingWeight' => 800, 'headingSpacing' => '-.4px', 'uppercase' => false, 'headingFont' => 'sans', 'pack' => 'soft'],
            ],
            'journal' => [
                'name' => 'Journal — Magazin',
                'description' => 'Serifen-Überschriften, schmale Lesebreite, zentrierter Kopf und feine Linien – redaktionell und edel.',
                'colors' => ['primary' => '#1c1917', 'accent' => '#b45309', 'text' => '#292524', 'bg' => '#fbf9f4', 'surface' => '#f0eadd'],
                'headerBg' => '#fbf9f4', 'headerText' => '#1c1917',
                'tokens' => ['header' => 'center', 'radius' => 3, 'button' => 'sharp', 'hero' => 58, 'container' => 900, 'section' => 46, 'shadow' => 'none', 'scale' => 18, 'headingWeight' => 700, 'headingSpacing' => '0', 'uppercase' => false, 'headingFont' => 'serif', 'pack' => 'editorial'],
            ],
            'diagonal' => [
                'name' => 'Diagonal — schräg & dynamisch',
                'description' => 'Diagonale Handschrift: farbige Bereiche und der Hero bekommen automatisch schräge Kanten, Karten haben angeschnittene Ecken, Akzente sind schräg – dynamisch und modern.',
                'colors' => ['primary' => '#0ea5e9', 'accent' => '#f43f5e', 'text' => '#0f172a', 'bg' => '#ffffff', 'surface' => '#eef4fb'],
                'headerBg' => '#0f172a', 'headerText' => '#ffffff',
                'tokens' => ['header' => 'bar', 'radius' => 8, 'button' => 'sharp', 'hero' => 72, 'container' => 1180, 'section' => 60, 'shadow' => 'soft', 'scale' => 16.5, 'headingWeight' => 800, 'headingSpacing' => '-.6px', 'uppercase' => false, 'headingFont' => 'sans', 'pack' => 'slant'],
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

        // Stil-Pack: gibt jedem Design eine eigene Element-Handschrift.
        $pack = self::packCss((string) ($t['pack'] ?? 'panel'));

        return $tokens . "\n" . $base . "\n" . $extra . "\n" . $pack;
    }

    /**
     * Komponenten-CSS je „Stil-Pack" – gestaltet alle Inhaltsblöcke (Über-
     * schriften, Text, Buttons, Zitate, Akkordeon, News-/Event-Karten, Preise,
     * Team, Trenner, Hero) im Stil des Designs, damit sich Designs nicht nur in
     * Farbe/Rundung, sondern in der gesamten Element-Optik unterscheiden.
     */
    public static function packCss(string $pack): string
    {
        return match ($pack) {
            'bold' => self::PACK_BOLD,
            'soft' => self::PACK_SOFT,
            'editorial' => self::PACK_EDITORIAL,
            'slant' => self::PACK_SLANT,
            default => self::PACK_PANEL,
        };
    }

    /* ---------- Stil-Packs (Komponenten-CSS) ---------- */

    private const PACK_PANEL = <<<'CSS'
.cms-heading.cms-v-accent-line::after{width:52px;height:3px}
.cms-card{background:var(--cms-bg);border:1px solid color-mix(in srgb,var(--cms-text) 12%,transparent);box-shadow:var(--cms-shadow)}
.cms-card:hover{transform:translateY(-3px)}
.cms-cards.is-list .cms-card,.cms-cards.is-minimal .cms-card{border:0;border-bottom:1px solid color-mix(in srgb,var(--cms-text) 12%,transparent);box-shadow:none;background:transparent}
.cms-card-date{letter-spacing:.5px}
.cms-text.cms-v-infobox{border:1px solid color-mix(in srgb,var(--cms-text) 10%,transparent)}
.cms-accordion details{background:var(--cms-bg)}
.cms-quote.cms-v-card{box-shadow:var(--cms-shadow)}
.cms-team-card,.cms-price-card{box-shadow:var(--cms-shadow)}
CSS;

    private const PACK_SOFT = <<<'CSS'
.cms-heading.cms-v-accent-line::after{height:6px;border-radius:999px;width:70px}
.cms-heading.cms-v-boxed{border-radius:16px}
.cms-text.cms-v-infobox{border-radius:22px;box-shadow:var(--cms-shadow)}
.cms-text.cms-v-note{border-left:0;border-radius:18px;padding-left:22px}
.cms-card{background:var(--cms-bg);border:0;border-radius:24px;box-shadow:var(--cms-shadow)}
.cms-card:hover{transform:translateY(-4px)}
.cms-card-img img{border-radius:0}
.cms-card-date{display:inline-block;background:color-mix(in srgb,var(--cms-primary) 14%,transparent);color:var(--cms-primary);padding:3px 12px;border-radius:999px;letter-spacing:0}
.cms-accordion details{border:0;background:var(--cms-surface);border-radius:18px;margin-bottom:12px;padding:2px 6px}
.cms-accordion summary::after{width:26px;height:26px;line-height:24px;text-align:center;border-radius:50%;background:color-mix(in srgb,var(--cms-primary) 14%,transparent)}
.cms-quote{border-left:0}
.cms-quote.cms-v-card{border-radius:24px;box-shadow:var(--cms-shadow)}
.cms-price-card,.cms-team-card,.cms-cd-cell{border-radius:24px;border:0;box-shadow:var(--cms-shadow)}
.cms-btn{box-shadow:0 8px 20px color-mix(in srgb,var(--cms-primary) 22%,transparent)}
CSS;

    private const PACK_BOLD = <<<'CSS'
.cms-heading{text-transform:uppercase}
.cms-heading.cms-v-accent-line::after{height:8px;width:80px;border-radius:0}
.cms-heading.cms-v-boxed{border-radius:0}
h1.cms-heading{font-size:3em}
.cms-text.cms-v-infobox{border-radius:0;border-left:8px solid var(--cms-primary);background:var(--cms-surface)}
.cms-text.cms-v-note{border-radius:0;border-left-width:8px}
.cms-btn{border-radius:0;text-transform:uppercase;letter-spacing:1px;padding:14px 30px}
.cms-btn-outline{border-width:3px}
.cms-card{background:var(--cms-bg);border:2px solid var(--cms-text);border-radius:0;box-shadow:none}
.cms-card:hover{transform:none;box-shadow:8px 8px 0 var(--cms-primary)}
.cms-card-img{border-bottom:2px solid var(--cms-text)}
.cms-card-date{display:inline-block;background:var(--cms-primary);color:#fff;padding:3px 10px;letter-spacing:1px}
.cms-cards.is-list .cms-card,.cms-cards.is-minimal .cms-card{border:0;border-bottom:3px solid var(--cms-text)}
.cms-accordion details{border:2px solid var(--cms-text);border-radius:0;margin-bottom:10px}
.cms-accordion summary{text-transform:uppercase;letter-spacing:.5px}
.cms-quote{border-left:8px solid var(--cms-primary);font-weight:600}
.cms-quote.cms-v-card{border-radius:0;border-left:8px solid var(--cms-primary);background:var(--cms-surface)}
.cms-price-card,.cms-team-card,.cms-cd-cell{border:2px solid var(--cms-text)!important;border-radius:0!important;box-shadow:none!important}
.cms-price-card.is-highlight{border-color:var(--cms-primary)!important}
.cms-divider{border-top-width:3px;border-color:var(--cms-text)}
CSS;

    private const PACK_EDITORIAL = <<<'CSS'
.cms-heading{font-family:Georgia,'Times New Roman',serif}
h1.cms-heading,h2.cms-heading{border-bottom:1px solid color-mix(in srgb,var(--cms-text) 25%,transparent);padding-bottom:.25em}
.cms-heading.cms-v-accent-line::after{display:none}
.cms-heading.cms-v-boxed{background:transparent;color:var(--cms-text);border-top:2px solid var(--cms-text);border-bottom:2px solid var(--cms-text);border-radius:0;padding:.2em 0}
.cms-text>p:first-child::first-letter{font-family:Georgia,serif;font-size:3.1em;line-height:.8;float:left;padding:6px 10px 0 0;color:var(--cms-primary);font-weight:700}
.cms-text.cms-v-infobox{background:transparent;border-top:2px solid var(--cms-text);border-bottom:2px solid var(--cms-text);border-radius:0}
.cms-btn{border-radius:0;text-transform:uppercase;letter-spacing:1.5px;font-size:.86em}
.cms-btn-primary{background:var(--cms-text)}
.cms-card{background:transparent;border:0;border-top:2px solid var(--cms-text);border-radius:0;box-shadow:none}
.cms-card:hover{transform:none}
.cms-card-body{padding-left:0;padding-right:0}
.cms-card-date{font-family:Georgia,serif;text-transform:none;font-style:italic;letter-spacing:0;color:color-mix(in srgb,var(--cms-text) 60%,transparent)}
.cms-cards h3{font-family:Georgia,serif}
.cms-accordion details{border:0;border-bottom:1px solid color-mix(in srgb,var(--cms-text) 25%,transparent);border-radius:0}
.cms-accordion summary{font-family:Georgia,serif}
.cms-quote{border-left:0;text-align:center;font-family:Georgia,serif}
.cms-quote.cms-v-big::before,.cms-quote::before{content:'\201C';display:block;font-family:Georgia,serif;font-size:3em;color:var(--cms-primary);line-height:.6}
.cms-quote.cms-v-card{background:transparent;border-top:2px solid var(--cms-text);border-bottom:2px solid var(--cms-text);border-radius:0;padding:22px 10px}
.cms-price-card,.cms-team-card{border:0!important;border-top:2px solid var(--cms-text)!important;border-radius:0!important;box-shadow:none!important;background:transparent!important}
.cms-team-card img{border-radius:0}
CSS;

    private const PACK_SLANT = <<<'CSS'
.cms-hero{position:relative}
.cms-hero::after{content:'';position:absolute;left:0;right:0;bottom:-1px;height:var(--cms-shape-h);background:var(--cms-bg);clip-path:polygon(0 100%,100% 0,100% 100%);z-index:3}
.cms-section:not([class*="cms-sec-"]){clip-path:polygon(0 0,100% 0,100% calc(100% - var(--cms-shape-h)),0 100%);padding-bottom:calc(var(--cms-section-space,0px) + var(--cms-shape-h) + 12px)}
.cms-heading.cms-v-accent-line::after{height:8px;width:64px;transform:skewX(-24deg)}
.cms-heading.cms-v-boxed{transform:skewX(-8deg)}
.cms-heading.cms-v-boxed>*{display:inline-block;transform:skewX(8deg)}
.cms-btn{clip-path:polygon(0 0,100% 0,100% 68%,90% 100%,0 100%)}
.cms-card{border:0;clip-path:polygon(0 0,100% 0,100% 100%,0 100%,0 0);border-radius:0;box-shadow:var(--cms-shadow);position:relative}
.cms-card::before{content:'';position:absolute;top:0;right:0;border-width:0 22px 22px 0;border-style:solid;border-color:var(--cms-primary) var(--cms-bg);z-index:2}
.cms-card:hover{transform:translateY(-3px)}
.cms-card-date{display:inline-block;background:var(--cms-primary);color:#fff;padding:2px 12px;transform:skewX(-12deg);letter-spacing:.5px}
.cms-card-date>*{display:inline-block;transform:skewX(12deg)}
.cms-text.cms-v-infobox{border-left:6px solid var(--cms-primary);border-radius:0}
.cms-quote{border-left:0;padding-left:0}
.cms-quote::before{content:'';display:block;width:44px;height:8px;background:var(--cms-primary);transform:skewX(-24deg);margin-bottom:12px}
.cms-quote.cms-v-card{border-radius:0;clip-path:polygon(0 0,100% 0,100% 100%,3% 100%)}
.cms-price-card,.cms-team-card{border-radius:0!important;clip-path:polygon(0 0,100% 0,100% 92%,94% 100%,0 100%)}
.cms-accordion details{border-radius:0}
.cms-accordion summary::after{transform:skewX(-16deg);color:var(--cms-primary)}
.cms-divider{border:0;height:10px;background:repeating-linear-gradient(-45deg,var(--cms-primary) 0 10px,transparent 10px 20px);opacity:.5}
CSS;

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
