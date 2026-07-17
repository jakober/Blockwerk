<?php
declare(strict_types=1);

namespace Core;

use Models\Layout;
use Models\Setting;

/**
 * Mitgelieferte Gesamt-Designs ("Themes"): komplette Pakete aus
 * Layout-HTML, Farbschema und Theme-CSS. Beim Aktivieren wird das
 * Standard-Layout (erstes Layout) überschrieben – alle Seiten, die es
 * nutzen, wechseln sofort die Optik. Inhalte bleiben unverändert; die
 * Inhaltsblöcke folgen über die CSS-Variablen automatisch den Farben.
 */
class Themes
{
    public static function all(): array
    {
        return [
            'klar' => [
                'name' => 'Klar',
                'description' => 'Hell und modern mit Indigo-Akzenten – das Standard-Design.',
                'colors' => ['primary' => '#4f46e5', 'accent' => '#f59e0b', 'text' => '#1e293b', 'bg' => '#ffffff', 'surface' => '#f1f5f9'],
                'headerBg' => '#ffffff', 'headerText' => '#0f172a',
            ],
            'mitternacht' => [
                'name' => 'Mitternacht',
                'description' => 'Dunkles Design mit Violett und Türkis – edel und ruhig.',
                'colors' => ['primary' => '#8b5cf6', 'accent' => '#22d3ee', 'text' => '#e2e8f0', 'bg' => '#0b1220', 'surface' => '#182238'],
                'headerBg' => '#0b1220', 'headerText' => '#f1f5f9',
            ],
            'magazin' => [
                'name' => 'Magazin',
                'description' => 'Zeitungs-Stil mit Serifen-Überschriften und rotem Akzent.',
                'colors' => ['primary' => '#b91c1c', 'accent' => '#d97706', 'text' => '#292524', 'bg' => '#fffdf8', 'surface' => '#f6f1e7'],
                'headerBg' => '#fffdf8', 'headerText' => '#1c1917',
            ],
            'natur' => [
                'name' => 'Natur',
                'description' => 'Warme Erd- und Grüntöne, weiche Formen – freundlich und organisch.',
                'colors' => ['primary' => '#4d7c0f', 'accent' => '#ca8a04', 'text' => '#2d3319', 'bg' => '#faf9f3', 'surface' => '#eef0e2'],
                'headerBg' => '#eef0e2', 'headerText' => '#2d3319',
            ],
            'studio' => [
                'name' => 'Studio',
                'description' => 'Radikal reduziert: Schwarz auf Weiß, große Typografie, klare Kanten.',
                'colors' => ['primary' => '#111111', 'accent' => '#ef4444', 'text' => '#111111', 'bg' => '#ffffff', 'surface' => '#f4f4f4'],
                'headerBg' => '#ffffff', 'headerText' => '#111111',
            ],
            'ozean' => [
                'name' => 'Ozean',
                'description' => 'Frisches Blau mit Farbverlauf im Kopfbereich – maritim und klar.',
                'colors' => ['primary' => '#0369a1', 'accent' => '#f59e0b', 'text' => '#0f2b3d', 'bg' => '#fbfeff', 'surface' => '#e3f2fb'],
                'headerBg' => 'linear-gradient(120deg, #082f49, #0369a1)', 'headerText' => '#ffffff',
            ],
        ];
    }

    public static function activeKey(): string
    {
        return Setting::get('active_theme', 'klar');
    }

    /** Aktiviert ein Theme: überschreibt das Standard-Layout. */
    public static function apply(string $key): bool
    {
        $theme = self::all()[$key] ?? null;
        if ($theme === null) {
            return false;
        }

        $design = json_encode([
            'colors' => $theme['colors'],
            'fonts' => [],
            'css' => self::css($key, $theme),
        ], JSON_UNESCAPED_UNICODE) ?: null;

        $layout = Layout::first();
        if ($layout === null) {
            Layout::create('Standard (' . $theme['name'] . ')', self::html($key), $design);
        } else {
            Layout::update((int) $layout['id'], 'Standard (' . $theme['name'] . ')', self::html($key), $design);
        }
        Setting::set('active_theme', $key);
        return true;
    }

    /* ---------- Layout-HTML pro Theme ---------- */

    private static function html(string $key): string
    {
        $header = match ($key) {
            // Magazin: Markenname zentriert, Menü darunter zwischen Linien.
            'magazin' => <<<'HTML'
<header class="t-header">
  <div class="container t-brandwrap"><a class="t-brand" href="{{base_url}}/">{{site_name}}</a></div>
  <nav class="t-nav"><div class="container">{{menu}}</div></nav>
</header>
HTML,
            // Alle anderen: Marke links, Menü rechts.
            default => <<<'HTML'
<header class="t-header">
  <div class="container t-headerbar">
    <a class="t-brand" href="{{base_url}}/">{{site_name}}</a>
    <nav class="t-nav">{{menu}}</nav>
  </div>
</header>
HTML,
        };

        return <<<HTML
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{title}} – {{site_name}}</title>
</head>
<body class="bw-t-$key">
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

    /* ---------- Theme-CSS ---------- */

    private static function css(string $key, array $theme): string
    {
        $headerBg = $theme['headerBg'];
        $headerText = $theme['headerText'];

        $base = <<<CSS
*{box-sizing:border-box}html,body{margin:0;padding:0}
body{font-size:16px;line-height:1.65}
.container{max-width:1100px;margin:0 auto;padding:0 20px}
a{color:var(--cms-primary)}
img{max-width:100%;height:auto}
.t-header{background:$headerBg;color:$headerText}
.t-headerbar{display:flex;align-items:center;justify-content:space-between;gap:24px;min-height:66px;flex-wrap:wrap}
.t-brand{font-size:21px;font-weight:800;letter-spacing:-.4px;color:inherit;text-decoration:none}
.t-nav ul.menu{display:flex;gap:4px;list-style:none;margin:0;padding:0;flex-wrap:wrap}
.t-nav li{position:relative}
.t-nav a{display:block;padding:8px 14px;color:inherit;text-decoration:none;font-weight:600;border-radius:8px}
.t-nav ul.submenu{display:none;position:absolute;top:100%;left:0;min-width:200px;background:var(--cms-bg);border:1px solid color-mix(in srgb,var(--cms-text) 15%,transparent);border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.15);list-style:none;margin:0;padding:6px;z-index:60;color:var(--cms-text)}
.t-nav li.has-children:hover>ul.submenu,.t-nav li.has-children:focus-within>ul.submenu{display:block}
.t-nav ul.submenu ul.submenu{top:0;left:100%}
.t-main{padding-top:36px;padding-bottom:56px}
.t-footer{padding:26px 0;font-size:14px}
CSS;

        $extra = match ($key) {
            'klar' => <<<'CSS'
.t-header{position:sticky;top:0;z-index:50;border-bottom:1px solid #e2e8f0}
.t-nav a:hover{background:var(--cms-surface);color:var(--cms-primary)}
.t-footer{border-top:1px solid #e2e8f0;color:#64748b}
CSS,
            'mitternacht' => <<<'CSS'
.t-header{position:sticky;top:0;z-index:50;border-bottom:1px solid #1e2a44;backdrop-filter:blur(8px)}
.t-nav a:hover{background:#1e2a44;color:var(--cms-accent)}
.t-footer{border-top:1px solid #1e2a44;color:#7c8db0}
.cms-card{border:1px solid #23304d}
.cms-form input,.cms-form textarea{background:var(--cms-surface)}
CSS,
            'magazin' => <<<'CSS'
h1,h2,h3,h4,h5,h6,.t-brand{font-family:Georgia,"Times New Roman",serif}
.t-brandwrap{text-align:center;padding:26px 20px 14px}
.t-brand{font-size:34px}
.t-nav{border-top:3px double #d6cbb6;border-bottom:1px solid #d6cbb6}
.t-nav ul.menu{justify-content:center}
.t-nav a{border-radius:0;text-transform:uppercase;font-size:13px;letter-spacing:1.5px}
.t-nav a:hover{color:var(--cms-primary)}
.t-footer{border-top:3px double #d6cbb6;color:#78716c;text-align:center}
.cms-heading{letter-spacing:0}
CSS,
            'natur' => <<<'CSS'
body{font-family:"Segoe UI",system-ui,sans-serif}
.t-header{border-radius:0 0 26px 26px}
.t-headerbar{min-height:76px}
.t-nav a{border-radius:999px}
.t-nav a:hover{background:var(--cms-primary);color:#fff}
.t-footer{background:var(--cms-surface);color:#5b6242}
.cms-image,.cms-card,.cms-gallery img{border-radius:22px}
.cms-btn{border-radius:999px}
CSS,
            'studio' => <<<'CSS'
.t-header{border-bottom:2px solid #111}
.t-brand{text-transform:uppercase;letter-spacing:3px;font-size:17px}
.t-nav a{border-radius:0;text-transform:uppercase;font-size:12px;letter-spacing:2px}
.t-nav a:hover{background:#111;color:#fff}
h1.cms-heading{font-size:3.1em;letter-spacing:-1px}
.t-footer{border-top:2px solid #111;color:#555;text-transform:uppercase;font-size:12px;letter-spacing:2px}
.cms-image,.cms-figure img,.cms-card,.cms-gallery img,.cms-video,.cms-btn,.cms-imgslider{border-radius:0!important}
.cms-btn-primary:hover{background:var(--cms-accent)}
CSS,
            'ozean' => <<<'CSS'
.t-header{box-shadow:0 4px 20px rgba(3,105,161,.25)}
.t-headerbar{min-height:74px}
.t-nav a:hover{background:rgba(255,255,255,.16)}
.t-footer{background:#082f49;color:#9cc7de;margin-top:40px}
.cms-card{box-shadow:0 4px 16px rgba(3,105,161,.08)}
h1.cms-heading,h2.cms-heading{color:#075985}
CSS,
            default => '',
        };

        return $base . "\n" . $extra . "\n";
    }
}
