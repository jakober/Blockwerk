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

    public function renderPage(array $page): string
    {
        $layout = $page['layout_id'] ? Layout::find((int) $page['layout_id']) : null;
        $layout ??= Layout::first();
        BlockRegistry::$pageId = (int) $page['id'];

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
        $layoutHtml = $layout['html'] ?? "<!doctype html>\n<html lang=\"de\"><head><meta charset=\"utf-8\"><title>{{title}}</title></head><body>{{content}}</body></html>";

        $html = $this->replacePlaceholders($layoutHtml, [
            'content' => $contentHtml,
            'title' => $title,
        ]);

        return $this->injectAssets($html, $layout, $extraHead);
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

            // Zeilen-Gestaltung: vollbreite Hintergrundfarbe und Innenabstände.
            $style = is_array($row['style'] ?? null) ? $row['style'] : [];
            $bg = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($style['bg'] ?? '')) ? strtolower((string) $style['bg']) : '';
            $pt = min(400, max(0, (int) ($style['pt'] ?? 0)));
            $pb = min(400, max(0, (int) ($style['pb'] ?? 0)));
            $padding = ($pt ? 'padding-top:' . $pt . 'px;' : '') . ($pb ? 'padding-bottom:' . $pb . 'px;' : '');

            if ($bg !== '') {
                $html .= '<div class="cms-section" style="background:' . $bg . ';' . $padding . '">'
                    . '<div class="cms-row">' . $inner . '</div></div>';
            } else {
                $html .= '<div class="cms-row"' . ($padding !== '' ? ' style="' . $padding . '"' : '') . '>' . $inner . '</div>';
            }
        }
        return $html;
    }

    /* ---------- Design (Farben & Schriften) + Block-Assets ---------- */

    private function injectAssets(string $html, ?array $layout, string $extraHead = ''): string
    {
        $head = '';
        if (stripos($html, 'cms-blocks.css') === false) {
            $head .= '<link rel="stylesheet" href="' . e(App::base()) . '/assets/css/cms-blocks.css">' . "\n";
        }
        $head .= $this->designHead($layout) . $extraHead;

        if ($head !== '' && ($pos = stripos($html, '</head>')) !== false) {
            $html = substr_replace($html, $head, $pos, 0);
        }

        if (stripos($html, 'cms-blocks.js') === false) {
            $script = '<script src="' . e(App::base()) . '/assets/js/cms-blocks.js" defer></script>' . "\n";
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

        // Menü mit wählbarer Darstellungsvariante:
        // {{menu}} bzw. {{menu:dropdown}} – klassisches Hover-Aufklappmenü
        // {{menu:mega}} – Mega-Menü (breites Panel mit Spalten für Unterpunkte)
        // {{menu:vertical}} – vertikale Liste mit eingerückten Ebenen (Sidebar/Footer)
        // {{menu:simple}} – nur die oberste Ebene, ohne Unterpunkte
        $html = preg_replace_callback(
            '/\{\{menu(?::([a-z]+))?\}\}/i',
            fn (array $m): string => $this->renderMenu(strtolower($m[1] ?? 'dropdown')),
            $html
        ) ?? $html;

        return strtr($html, [
            '{{content}}' => $vars['content'] ?? '',
            '{{title}}' => e($vars['title'] ?? ''),
            '{{site_name}}' => e(Setting::get('site_name', 'Meine Website')),
            '{{base_url}}' => App::base(),
            '{{year}}' => date('Y'),
        ]);
    }

    /* ---------- Menü (Baumstruktur, mehrere Darstellungen) ---------- */

    private function renderMenu(string $style): string
    {
        if (!in_array($style, ['dropdown', 'mega', 'vertical', 'simple'], true)) {
            $style = 'dropdown';
        }

        $byParent = [];
        foreach (Page::menuPages() as $page) {
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
            $html .= '<a href="' . e(url('/' . $page['slug'])) . '">' . e($page['title']) . '</a>';
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
            $html .= '<li><a href="' . e(url('/' . $page['slug'])) . '">' . e($page['title']) . '</a></li>';
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
            $html .= '<a href="' . e(url('/' . $page['slug'])) . '">' . e($page['title']) . '</a>';

            if ($children !== []) {
                $html .= '<div class="cms-mega-panel">';
                foreach ($children as $child) {
                    $html .= '<div class="cms-mega-col">';
                    $html .= '<a class="cms-mega-head" href="' . e(url('/' . $child['slug'])) . '">' . e($child['title']) . '</a>';
                    $grandchildren = $byParent[(int) $child['id']] ?? [];
                    if ($grandchildren !== []) {
                        $html .= '<ul>';
                        foreach ($grandchildren as $grandchild) {
                            $html .= '<li><a href="' . e(url('/' . $grandchild['slug'])) . '">' . e($grandchild['title']) . '</a></li>';
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
