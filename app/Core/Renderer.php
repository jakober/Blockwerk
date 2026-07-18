<?php
declare(strict_types=1);

namespace Core;

use Models\Font;
use Models\Layout;
use Models\Page;
use Models\Setting;
use Models\Template;

/**
 * Rendert öffentliche Seiten: Layout laden, Platzhalter ersetzen,
 * Templates einbetten und den Seiteninhalt (Zeilen/Spalten/Blöcke) aufbauen.
 *
 * Unterstützte Platzhalter in Layouts und Templates:
 *   {{content}}        Seiteninhalt (nur im Layout sinnvoll)
 *   {{title}}          Seitentitel
 *   {{site_name}}      Name der Website (Einstellungen)
 *   {{base_url}}       Basis-URL der Installation
 *   {{year}}           aktuelles Jahr
 *   {{menu}}           Hauptmenü aus allen Seiten mit "im Menü anzeigen"
 *   {{template:key}}   bettet das Template mit diesem Schlüssel ein
 *
 * Zusätzlich werden automatisch injiziert:
 *   - cms-blocks.css / cms-blocks.js (Struktur & Interaktion der Blöcke)
 *   - ein <style>-Block mit den im Layout-Designer gewählten Farben und
 *     Schriften als CSS-Variablen (--cms-primary, --cms-font-body, …)
 *   - <link>-Tags für lokal gespeicherte Google Fonts
 */
class Renderer
{
    private const MAX_TEMPLATE_DEPTH = 5;

    /** Aktuelle Sprache der gerenderten Seite (für Menü & Sprachumschalter). */
    public static ?string $lang = null;

    public static function lang(): string
    {
        return self::$lang ?? cms_default_lang();
    }

    /** Beliebiges HTML im Standard-Layout ausgeben (Suche, Fehlerseiten …). */
    public function renderRaw(string $title, string $html): string
    {
        return $this->renderWithLayout(Layout::first(), $title, $html);
    }

    public function renderPage(array $page): string
    {
        $layout = $page['layout_id'] ? Layout::find((int) $page['layout_id']) : null;
        $layout ??= Layout::first();
        BlockRegistry::$pageId = (int) $page['id'];
        self::$lang ??= (string) ($page['lang'] ?? cms_default_lang());

        $data = json_decode((string) ($page['content'] ?? ''), true);
        $extraHead = $this->seoHead($page);
        if (is_array($data) && is_string($data['css'] ?? null) && trim($data['css']) !== '') {
            $extraHead .= '<style id="cms-page-css">' . $data['css'] . '</style>' . "\n";
        }

        $title = trim((string) ($page['meta_title'] ?? '')) !== '' ? (string) $page['meta_title'] : (string) $page['title'];
        return $this->renderWithLayout($layout, $title, $this->renderContentData($data), $extraHead);
    }

    /** SEO-Metatags aus den Seiten-Einstellungen. */
    private function seoHead(array $page): string
    {
        $head = '';
        $description = trim((string) ($page['meta_description'] ?? ''));
        if ($description !== '') {
            $head .= '<meta name="description" content="' . e($description) . '">' . "\n";
            $head .= '<meta property="og:description" content="' . e($description) . '">' . "\n";
        }
        $head .= '<meta property="og:title" content="' . e(trim((string) ($page['meta_title'] ?? '')) !== '' ? $page['meta_title'] : $page['title']) . '">' . "\n";
        if (!empty($page['noindex'])) {
            $head .= '<meta name="robots" content="noindex, nofollow">' . "\n";
        }
        return $head;
    }

    /** Detailseite für News/Events – nutzt das erste Layout. */
    public function renderPost(array $post, string $type): string
    {
        $html = '<article class="cms-post">';
        $html .= '<h1 class="cms-heading">' . e((string) $post['title']) . '</h1>';

        $meta = [];
        if ($type === 'events') {
            $date = format_date_de($post['start_at'] ?? null, true);
            if (!empty($post['end_at'])) {
                $date .= ' – ' . format_date_de($post['end_at'], true);
            }
            if ($date !== '') {
                $meta[] = '🗓 ' . $date;
            }
            if (!empty($post['location'])) {
                $meta[] = '📍 ' . $post['location'];
            }
        } else {
            $date = format_date_de($post['published_at'] ?? $post['created_at'] ?? null);
            if ($date !== '') {
                $meta[] = $date;
            }
        }
        if ($meta !== []) {
            $html .= '<div class="cms-post-meta">' . e(implode('   ·   ', $meta)) . '</div>';
        }
        if (!empty($post['image'])) {
            $html .= '<img class="cms-post-image" src="' . e((string) $post['image']) . '" alt="' . e((string) $post['title']) . '">';
        }
        $html .= '<div class="cms-text">' . (string) ($post['body'] ?? '') . '</div>';
        $html .= '</article>';

        return $this->renderWithLayout(Layout::first(), (string) $post['title'], $html);
    }

    private function renderWithLayout(?array $layout, string $title, string $contentHtml, string $extraHead = ''): string
    {
        // Visueller Layout-Baukasten: Layout-HTML aus der Zeilen/Spalten-
        // Struktur erzeugen (enthält Platzhalter wie {{content}}, {{menu:…}}).
        $builder = json_decode((string) ($layout['builder'] ?? ''), true);
        if (is_array($builder) && !empty($builder['rows'])) {
            $layoutHtml = "<!doctype html>\n<html lang=\"de\">\n<head>\n<meta charset=\"utf-8\">\n"
                . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
                . "<title>{{title}} – {{site_name}}</title>\n</head>\n<body class=\"bwl-body\">\n"
                . '<div class="bwl-page">' . $this->renderContentData($builder) . "</div>\n</body>\n</html>";
            if (is_string($builder['css'] ?? null) && trim($builder['css']) !== '') {
                $extraHead .= '<style id="cms-layout-builder-css">' . $builder['css'] . '</style>' . "\n";
            }
        } else {
            $layoutHtml = $layout['html'] ?? "<!doctype html>\n<html lang=\"de\"><head><meta charset=\"utf-8\"><title>{{title}}</title></head><body>{{content}}</body></html>";
        }

        $html = $this->replacePlaceholders($layoutHtml, [
            'content' => $contentHtml,
            'title' => $title,
        ]);

        return $this->injectAssets($html, $layout, $extraHead);
    }

    /** Platzhalter für die Editor-Vorschau auflösen (echtes Menü, Marke usw.). */
    public function fillForPreview(string $html): string
    {
        if (!str_contains($html, '{{')) {
            return $html;
        }
        return $this->replacePlaceholders($html, [
            'content' => '<div class="bwl-content-ph">▣ Hier erscheint der Inhalt der jeweiligen Seite</div>',
            'title' => 'Seitentitel',
        ]);
    }

    public function renderContent(?string $json): string
    {
        return $this->renderContentData(json_decode((string) $json, true));
    }

    private function renderContentData(mixed $data): string
    {
        if (!is_array($data) || !is_array($data['rows'] ?? null)) {
            return '';
        }

        $html = '';
        $mediaRules = [];
        foreach ($data['rows'] as $row) {
            if (!is_array($row['columns'] ?? null)) {
                continue;
            }
            $inner = '';
            foreach ($row['columns'] as $column) {
                $span = min(12, max(1, (int) ($column['span'] ?? 12)));
                $inner .= '<div class="cms-col" style="--span:' . $span . '">';
                foreach (($column['blocks'] ?? []) as $block) {
                    if (is_array($block)) {
                        $inner .= BlockRegistry::render($block);
                    }
                }
                $inner .= '</div>';
            }

            // Zeilen-Gestaltung: Hintergrundfarbe (frei oder aus der
            // Layout-Palette), Breite (Inhaltsbreite oder volle Seite)
            // und Innenabstände.
            $style = is_array($row['style'] ?? null) ? $row['style'] : [];
            $palette = [
                'primary' => 'var(--cms-primary)',
                'accent' => 'var(--cms-accent)',
                'surface' => 'var(--cms-surface)',
                'page' => 'var(--cms-bg)',
            ];
            $bgRaw = (string) ($style['bg'] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $bgRaw)) {
                $bg = strtolower($bgRaw);
            } else {
                $bg = $palette[$bgRaw] ?? '';
            }
            $full = ($style['width'] ?? '') === 'full';
            $pt = min(400, max(0, (int) ($style['pt'] ?? 0)));
            $pb = min(400, max(0, (int) ($style['pb'] ?? 0)));
            $padding = ($pt ? 'padding-top:' . $pt . 'px;' : '') . ($pb ? 'padding-bottom:' . $pb . 'px;' : '');

            // Eigener Stapel-Breakpoint: ab wie viel Pixeln die Spalten
            // untereinander rutschen. Leer = Standard (768), 0 = nie.
            $bpAttr = '';
            if (isset($style['bp']) && $style['bp'] !== '') {
                $bp = min(2000, max(0, (int) $style['bp']));
                $bpAttr = ' data-bp="' . $bp . '"';
                if ($bp > 0) {
                    $mediaRules[$bp] = '@media (max-width: ' . $bp . 'px){.cms-row[data-bp="' . $bp . '"] > .cms-col{grid-column:span 12;}}';
                }
            }

            if ($bg !== '' || $full) {
                $sectionStyle = ($bg !== '' ? 'background:' . $bg . ';' : '') . $padding;
                $html .= '<div class="cms-section"' . ($sectionStyle !== '' ? ' style="' . $sectionStyle . '"' : '') . '>'
                    . '<div class="cms-row' . ($full ? ' cms-row-full' : '') . '"' . $bpAttr . '>' . $inner . '</div></div>';
            } else {
                $html .= '<div class="cms-row"' . $bpAttr . ($padding !== '' ? ' style="' . $padding . '"' : '') . '>' . $inner . '</div>';
            }
        }
        if ($mediaRules !== []) {
            $html .= '<style>' . implode('', $mediaRules) . '</style>';
        }
        return $html;
    }

    /* ---------- Design (Farben & Schriften) + Block-Assets ---------- */

    private function injectAssets(string $html, ?array $layout, string $extraHead = ''): string
    {
        $head = '';
        // Ohne Viewport-Meta rendern Smartphones die Seite als Desktop –
        // dann greift u. a. das mobile Menü (Breakpoint) nie.
        if (stripos($html, 'name="viewport"') === false && stripos($html, "name='viewport'") === false) {
            $head .= '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
        }
        if (stripos($html, 'cms-blocks.css') === false) {
            $head .= '<link rel="stylesheet" href="' . e(App::base()) . '/assets/css/cms-blocks.css?v=' . e(rawurlencode(cms_version())) . '">' . "\n";
        }
        $head .= $this->designHead($layout) . $extraHead;

        if ($head !== '' && ($pos = stripos($html, '</head>')) !== false) {
            $html = substr_replace($html, $head, $pos, 0);
        }

        if (stripos($html, 'cms-blocks.js') === false) {
            $script = '<script src="' . e(App::base()) . '/assets/js/cms-blocks.js?v=' . e(rawurlencode(cms_version())) . '" defer></script>' . "\n";
            if (($pos = stripos($html, '</body>')) !== false) {
                $html = substr_replace($html, $script, $pos, 0);
            } else {
                $html .= $script;
            }
        }
        return $html;
    }

    /** Font-Links, CSS-Variablen und optionales Layout-CSS für den <head>. */
    public function designHead(?array $layout): string
    {
        $design = json_decode((string) ($layout['design'] ?? ''), true);
        if (!is_array($design)) {
            return '';
        }

        $out = '';
        $vars = [];

        $colorMap = ['primary' => '--cms-primary', 'accent' => '--cms-accent',
            'text' => '--cms-text', 'bg' => '--cms-bg', 'surface' => '--cms-surface'];
        foreach ($colorMap as $key => $var) {
            $value = (string) ($design['colors'][$key] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $vars[$var] = $value;
            }
        }

        foreach (['heading' => '--cms-font-heading', 'body' => '--cms-font-body'] as $key => $var) {
            $fontId = (int) ($design['fonts'][$key] ?? 0);
            if ($fontId > 0 && ($font = Font::find($fontId)) !== null) {
                $out .= '<link rel="stylesheet" href="' . e(App::base() . '/uploads/fonts/' . $font['folder'] . '/font.css') . '">' . "\n";
                $vars[$var] = "'" . str_replace("'", '', $font['family']) . "', sans-serif";
            }
        }

        if ($vars !== []) {
            $css = ':root{';
            foreach ($vars as $name => $value) {
                $css .= $name . ':' . $value . ';';
            }
            $out .= '<style id="cms-design">' . $css . '}</style>' . "\n";
        }
        if (is_string($design['css'] ?? null) && trim($design['css']) !== '') {
            $out .= '<style id="cms-layout-css">' . $design['css'] . '</style>' . "\n";
        }
        return $out;
    }

    /* ---------- Platzhalter ---------- */

    private function replacePlaceholders(string $html, array $vars, int $depth = 0): string
    {
        $html = preg_replace_callback(
            '/\{\{template:([a-z0-9\-_]+)\}\}/i',
            function (array $m) use ($vars, $depth): string {
                if ($depth >= self::MAX_TEMPLATE_DEPTH) {
                    return '';
                }
                $template = Template::findByKey($m[1]);
                if ($template === null) {
                    return '<!-- Template "' . e($m[1]) . '" nicht gefunden -->';
                }
                return $this->replacePlaceholders($template['html'], $vars, $depth + 1);
            },
            $html
        ) ?? $html;

        // Globale Blöcke im Layout/Template: {{global:ID}} (ID steht in der
        // Liste unter "Globale Blöcke") – erscheint damit auf jeder Seite.
        $html = preg_replace_callback(
            '/\{\{global:(\d+)\}\}/',
            static fn (array $m): string => BlockRegistry::render(['type' => 'global', 'data' => ['page_id' => (int) $m[1]]]),
            $html
        ) ?? $html;

        // {{menu}} ohne Variante: wird komplett vom Menü-Designer gestaltet
        // (Vorlage, Farben, Breakpoint) – so folgen auch Layouts und Design-
        // Themes, die {{menu}} direkt einbinden, den Einstellungen.
        if (str_contains($html, '{{menu}}')) {
            $html = str_replace('{{menu}}', BlockRegistry::menuHtml(MenuDesign::stored()), $html);
        }

        // Menü mit expliziter Darstellungsvariante:
        // {{menu:dropdown}} – klassisches Hover-Aufklappmenü
        // {{menu:mega}} – Mega-Menü (breites Panel mit Spalten für Unterpunkte)
        // {{menu:vertical}} – vertikale Liste mit eingerückten Ebenen (Sidebar/Footer)
        // {{menu:simple}} – nur die oberste Ebene, ohne Unterpunkte
        $html = preg_replace_callback(
            '/\{\{menu:([a-z]+)\}\}/i',
            fn (array $m): string => $this->renderMenu(strtolower($m[1])),
            $html
        ) ?? $html;

        return strtr($html, [
            '{{content}}' => $vars['content'] ?? '',
            '{{title}}' => e($vars['title'] ?? ''),
            '{{site_name}}' => e(Setting::get('site_name', 'Meine Website')),
            '{{base_url}}' => App::base(),
            '{{year}}' => date('Y'),
            '{{languages}}' => $this->renderLanguageSwitcher(),
        ]);
    }

    /** Sprachumschalter ({{languages}}) – nur sichtbar bei mehreren Sprachen. */
    private function renderLanguageSwitcher(): string
    {
        $langs = cms_langs();
        if (count($langs) < 2) {
            return '';
        }
        $html = '<ul class="cms-langswitch">';
        foreach ($langs as $lang) {
            $href = $lang === cms_default_lang() ? url('/') : url('/' . $lang);
            $active = $lang === self::lang() ? ' class="is-active"' : '';
            $html .= '<li' . $active . '><a href="' . e($href) . '">' . e(strtoupper($lang)) . '</a></li>';
        }
        return $html . '</ul>';
    }

    /* ---------- Menü (Baumstruktur, mehrere Darstellungen) ---------- */

    private function renderMenu(string $style): string
    {
        if (!in_array($style, ['dropdown', 'mega', 'vertical', 'simple'], true)) {
            $style = 'dropdown';
        }

        $byParent = [];
        foreach (Page::menuPages(self::lang()) as $page) {
            $byParent[(int) ($page['parent_id'] ?? 0)][] = $page;
        }

        return match ($style) {
            'simple' => $this->menuSimple($byParent),
            'mega' => $this->menuMega($byParent),
            'vertical' => $this->menuNested($byParent, 0, 'menu cms-menu cms-menu-vertical'),
            default => $this->menuNested($byParent, 0, 'menu cms-menu cms-menu-dropdown'),
        };
    }

    private function menuNested(array $byParent, int $parentId, string $class): string
    {
        if (empty($byParent[$parentId])) {
            return '';
        }
        $html = '<ul class="' . $class . '">';
        foreach ($byParent[$parentId] as $page) {
            $children = $this->menuNested($byParent, (int) $page['id'], 'submenu');
            $html .= '<li' . ($children !== '' ? ' class="has-children"' : '') . '>';
            $html .= '<a href="' . e(page_url($page)) . '">' . e($page['title']) . '</a>';
            $html .= $children . '</li>';
        }
        return $html . '</ul>';
    }

    private function menuSimple(array $byParent): string
    {
        if (empty($byParent[0])) {
            return '';
        }
        $html = '<ul class="menu cms-menu cms-menu-simple">';
        foreach ($byParent[0] as $page) {
            $html .= '<li><a href="' . e(page_url($page)) . '">' . e($page['title']) . '</a></li>';
        }
        return $html . '</ul>';
    }

    /**
     * Mega-Menü: Hauptpunkte mit Unterseiten öffnen ein breites Panel,
     * in dem jede Unterseite eine Spalte mit ihren eigenen Unterpunkten ist.
     */
    private function menuMega(array $byParent): string
    {
        if (empty($byParent[0])) {
            return '';
        }
        $html = '<ul class="menu cms-menu cms-menu-mega">';
        foreach ($byParent[0] as $page) {
            $children = $byParent[(int) $page['id']] ?? [];
            $html .= '<li' . ($children !== [] ? ' class="has-children"' : '') . '>';
            $html .= '<a href="' . e(page_url($page)) . '">' . e($page['title']) . '</a>';

            if ($children !== []) {
                $html .= '<div class="cms-mega-panel">';
                foreach ($children as $child) {
                    $html .= '<div class="cms-mega-col">';
                    $html .= '<a class="cms-mega-head" href="' . e(page_url($child)) . '">' . e($child['title']) . '</a>';
                    $grandchildren = $byParent[(int) $child['id']] ?? [];
                    if ($grandchildren !== []) {
                        $html .= '<ul>';
                        foreach ($grandchildren as $grandchild) {
                            $html .= '<li><a href="' . e(page_url($grandchild)) . '">' . e($grandchild['title']) . '</a></li>';
                        }
                        $html .= '</ul>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
            $html .= '</li>';
        }
        return $html . '</ul>';
    }
}
