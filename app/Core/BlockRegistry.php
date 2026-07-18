<?php
declare(strict_types=1);

namespace Core;

use Models\Post;

/**
 * Zentrale Registrierung aller Inhalts-Block-Typen.
 * Neue Block-Typen: hier einen Render-Eintrag ergänzen und in
 * public/assets/js/editor.js einen passenden Eintrag in blockDefs anlegen.
 *
 * Viele Blöcke haben eine Designvorlage ("variant"), die als CSS-Klasse
 * cms-v-<variant> ausgegeben wird. Die zugehörigen Styles in
 * public/assets/css/cms-blocks.css nutzen die Layout-Farben (CSS-Variablen
 * --cms-primary, --cms-accent, …), sodass sich alle Varianten automatisch
 * nach dem gewählten Farbschema richten.
 */
class BlockRegistry
{
    /** Seiten-Kontext für Blöcke, die ihn brauchen (z. B. Formular-Ziel). */
    public static ?int $pageId = null;

    public static function types(): array
    {
        return ['heading', 'text', 'image', 'gallery', 'slider', 'hero', 'button',
            'video', 'quote', 'accordion', 'news', 'events', 'form', 'search', 'global',
            'map', 'team', 'pricing', 'countdown', 'social', 'html', 'divider', 'spacer',
            // Layout-Blöcke (visueller Layout-Baukasten)
            'l-brand', 'l-menu', 'l-content', 'l-languages'];
    }

    /**
     * Kompletten Inhalts-Baum (rows → columns → blocks, optional css)
     * bereinigen – genutzt vom Seiten-Editor UND dem Layout-Baukasten.
     */
    public static function sanitizeTree(array $data): array
    {
        $rows = [];
        foreach (($data['rows'] ?? []) as $row) {
            if (!is_array($row) || !is_array($row['columns'] ?? null)) {
                continue;
            }
            $columns = [];
            foreach ($row['columns'] as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $blocks = [];
                foreach (($column['blocks'] ?? []) as $block) {
                    if (!is_array($block) || !in_array($block['type'] ?? '', self::types(), true)) {
                        continue;
                    }
                    $blocks[] = [
                        'id' => substr((string) ($block['id'] ?? uniqid('b-')), 0, 40),
                        'type' => $block['type'],
                        'data' => self::sanitizeData((array) ($block['data'] ?? [])),
                    ];
                }
                $columns[] = [
                    'id' => substr((string) ($column['id'] ?? uniqid('col-')), 0, 40),
                    'span' => min(12, max(1, (int) ($column['span'] ?? 12))),
                    'blocks' => $blocks,
                ];
            }
            if ($columns !== []) {
                $rowOut = [
                    'id' => substr((string) ($row['id'] ?? uniqid('row-')), 0, 40),
                    'columns' => $columns,
                ];
                $rowStyle = [];
                foreach ((array) ($row['style'] ?? []) as $styleKey => $styleValue) {
                    if (is_scalar($styleValue)) {
                        $rowStyle[(string) $styleKey] = is_bool($styleValue) ? (int) $styleValue : $styleValue;
                    }
                }
                if ($rowStyle !== []) {
                    $rowOut['style'] = $rowStyle;
                }
                $rows[] = $rowOut;
            }
        }

        $clean = ['rows' => $rows];
        if (is_string($data['css'] ?? null) && trim($data['css']) !== '') {
            $clean['css'] = substr($data['css'], 0, 100000);
        }
        return $clean;
    }

    public static function render(array $block): string
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $html = self::renderInner($block, $data);
        if ($html === '') {
            return '';
        }
        // Grafische Einstellungen ohne CSS: Abstände, Farben, Ausrichtung,
        // Eckenrundung aus data._style als Inline-Style-Wrapper.
        $style = self::styleAttr(is_array($data['_style'] ?? null) ? $data['_style'] : []);
        if ($style !== '') {
            return '<div class="cms-block" style="' . $style . '">' . $html . '</div>';
        }
        return $html;
    }

    /**
     * Block-Daten bereinigen: Skalare, Objekt-Maps (z. B. _style) und
     * Element-Listen (Galerie-Bilder, Slides, …) mit skalaren Werten –
     * tiefere Verschachtelung wird verworfen.
     */
    public static function sanitizeData(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $clean[(string) $key] = is_bool($value) ? (int) $value : $value;
            } elseif (is_array($value)) {
                if (array_is_list($value)) {
                    $items = [];
                    foreach ($value as $item) {
                        if (!is_array($item)) {
                            continue;
                        }
                        $itemClean = [];
                        foreach ($item as $itemKey => $itemValue) {
                            if (is_scalar($itemValue)) {
                                $itemClean[(string) $itemKey] = is_bool($itemValue) ? (int) $itemValue : $itemValue;
                            }
                        }
                        $items[] = $itemClean;
                    }
                    $clean[(string) $key] = $items;
                } else {
                    $map = [];
                    foreach ($value as $mapKey => $mapValue) {
                        if (is_scalar($mapValue)) {
                            $map[(string) $mapKey] = is_bool($mapValue) ? (int) $mapValue : $mapValue;
                        }
                    }
                    $clean[(string) $key] = $map;
                }
            }
        }
        return $clean;
    }

    private static function styleAttr(array $style): string
    {
        $css = '';
        $lengths = ['mt' => 'margin-top', 'mb' => 'margin-bottom', 'p' => 'padding', 'radius' => 'border-radius'];
        foreach ($lengths as $key => $prop) {
            if (isset($style[$key]) && $style[$key] !== '' && (int) $style[$key] > 0) {
                $css .= $prop . ':' . min(400, (int) $style[$key]) . 'px;';
            }
        }
        foreach (['color' => 'color', 'bg' => 'background'] as $key => $prop) {
            $value = (string) ($style[$key] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $css .= $prop . ':' . strtolower($value) . ';';
            }
        }
        if (in_array($style['align'] ?? '', ['left', 'center', 'right'], true)) {
            $css .= 'text-align:' . $style['align'] . ';';
        }
        if (str_contains($css, 'border-radius') || str_contains($css, 'background')) {
            $css .= 'overflow:hidden;';
        }
        return $css;
    }

    private static function renderInner(array $block, array $data): string
    {
        return match ($block['type'] ?? '') {
            'heading' => self::heading($data),
            'text' => '<div class="cms-text' . self::variant($data) . '">' . (string) ($data['html'] ?? '') . '</div>',
            'image' => self::image($data),
            'gallery' => self::gallery($data),
            'slider' => self::slider($data),
            'hero' => self::hero($data),
            'button' => self::button($data),
            'video' => self::video($data),
            'quote' => self::quote($data),
            'accordion' => self::accordion($data),
            'news' => self::news($data),
            'events' => self::events($data),
            'form' => self::form($block, $data),
            'search' => self::searchForm($data),
            'global' => self::globalBlock($data),
            'map' => self::map($data),
            'team' => self::team($data),
            'pricing' => self::pricing($data),
            'countdown' => self::countdown($data),
            'social' => self::social($data),
            // Layout-Blöcke: geben Platzhalter aus, die der Renderer beim
            // Seitenaufbau (bzw. die Vorschau im Editor) auflöst.
            'l-brand' => self::lBrand($data),
            'l-menu' => self::lMenu($data),
            'l-content' => '<div class="bwl-content">{{content}}</div>',
            'l-languages' => '{{languages}}',
            'html' => (string) ($data['code'] ?? ''),
            'divider' => '<hr class="cms-divider">',
            'spacer' => '<div class="cms-spacer" style="height:' . max(0, (int) ($data['height'] ?? 40)) . 'px"></div>',
            default => '',
        };
    }

    private static function variant(array $data): string
    {
        $variant = (string) ($data['variant'] ?? '');
        if ($variant === '' || $variant === 'standard') {
            return '';
        }
        return ' cms-v-' . preg_replace('/[^a-z0-9\-]/', '', strtolower($variant));
    }

    /** @return array<int, array<string, mixed>> */
    private static function items(array $data, string $key): array
    {
        $items = $data[$key] ?? [];
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    private static function heading(array $data): string
    {
        $level = $data['level'] ?? 'h2';
        $level = in_array($level, ['h1', 'h2', 'h3', 'h4'], true) ? $level : 'h2';
        return "<{$level} class=\"cms-heading" . self::variant($data) . '">'
            . e((string) ($data['text'] ?? '')) . "</{$level}>";
    }

    private static function image(array $data): string
    {
        $src = (string) ($data['src'] ?? '');
        if ($src === '') {
            return '';
        }
        $img = '<img class="cms-image" src="' . e($src) . '" alt="' . e((string) ($data['alt'] ?? '')) . '" loading="lazy">';
        if (!empty($data['link'])) {
            $img = '<a href="' . e((string) $data['link']) . '">' . $img . '</a>';
        }
        $caption = (string) ($data['caption'] ?? '');
        $html = '<figure class="cms-figure' . self::variant($data) . '">' . $img;
        if ($caption !== '') {
            $html .= '<figcaption>' . e($caption) . '</figcaption>';
        }
        return $html . '</figure>';
    }

    private static function gallery(array $data): string
    {
        $images = self::items($data, 'images');
        if ($images === []) {
            return '';
        }
        $cols = min(6, max(1, (int) ($data['columns'] ?? 3)));
        $lightbox = !empty($data['lightbox']);
        $showCaptions = !empty($data['show_captions']);

        $html = '<div class="cms-gallery' . self::variant($data) . '" style="--cols:' . $cols . '"'
            . ($lightbox ? ' data-lightbox' : '') . '>';
        foreach ($images as $img) {
            $src = (string) ($img['src'] ?? '');
            if ($src === '') {
                continue;
            }
            $alt = e((string) ($img['alt'] ?? $img['caption'] ?? ''));
            $caption = (string) ($img['caption'] ?? '');
            $tag = '<img src="' . e($src) . '" alt="' . $alt . '" loading="lazy">';
            if ($lightbox) {
                $tag = '<a href="' . e($src) . '" class="cms-gl-link" data-caption="' . e($caption) . '">' . $tag . '</a>';
            }
            $html .= '<figure class="cms-gl-item">' . $tag;
            if ($showCaptions && $caption !== '') {
                $html .= '<figcaption>' . e($caption) . '</figcaption>';
            }
            $html .= '</figure>';
        }
        return $html . '</div>';
    }

    private static function sliderAttrs(array $data, int $defaultInterval = 5): string
    {
        $attrs = ' data-slider';
        if (!empty($data['autoplay'])) {
            $attrs .= ' data-autoplay data-interval="' . max(2, (int) ($data['interval'] ?? $defaultInterval)) . '"';
        }
        if (!empty($data['arrows'])) {
            $attrs .= ' data-arrows';
        }
        if (!empty($data['dots'])) {
            $attrs .= ' data-dots';
        }
        return $attrs;
    }

    private static function slider(array $data): string
    {
        $images = self::items($data, 'images');
        if ($images === []) {
            return '';
        }
        $height = min(900, max(160, (int) ($data['height'] ?? 420)));

        $html = '<div class="cms-imgslider"' . self::sliderAttrs($data) . ' style="--h:' . $height . 'px">';
        foreach ($images as $i => $img) {
            $src = (string) ($img['src'] ?? '');
            if ($src === '') {
                continue;
            }
            $caption = (string) ($img['caption'] ?? '');
            $html .= '<div class="cms-slide' . ($i === 0 ? ' is-active' : '') . '">';
            $html .= '<img src="' . e($src) . '" alt="' . e($caption) . '" loading="lazy">';
            if ($caption !== '') {
                $html .= '<div class="cms-slide-caption">' . e($caption) . '</div>';
            }
            $html .= '</div>';
        }
        return $html . '</div>';
    }

    private static function hero(array $data): string
    {
        $slides = self::items($data, 'slides');
        if ($slides === []) {
            return '';
        }
        $height = min(100, max(30, (int) ($data['height'] ?? 65)));
        $overlay = $data['overlay'] ?? 'medium';
        $overlay = in_array($overlay, ['none', 'light', 'medium', 'dark'], true) ? $overlay : 'medium';

        $html = '<div class="cms-hero cms-fullwidth"' . self::sliderAttrs($data, 6)
            . ' style="--hero-h:' . $height . 'vh">';
        foreach ($slides as $i => $slide) {
            $html .= '<div class="cms-slide' . ($i === 0 ? ' is-active' : '') . '"'
                . (!empty($slide['src']) ? ' style="background-image:url(\'' . e((string) $slide['src']) . '\')"' : '') . '>';
            $html .= '<div class="cms-hero-overlay is-' . $overlay . '"></div>';
            $html .= '<div class="cms-hero-content">';
            if (!empty($slide['title'])) {
                $html .= '<h2>' . e((string) $slide['title']) . '</h2>';
            }
            if (!empty($slide['text'])) {
                $html .= '<p>' . e((string) $slide['text']) . '</p>';
            }
            if (!empty($slide['button_text'])) {
                $html .= '<a class="cms-btn cms-btn-primary cms-btn-lg" href="'
                    . e((string) ($slide['button_url'] ?? '#')) . '">' . e((string) $slide['button_text']) . '</a>';
            }
            $html .= '</div></div>';
        }
        return $html . '</div>';
    }

    private static function button(array $data): string
    {
        $style = $data['style'] ?? 'primary';
        $style = in_array($style, ['primary', 'outline', 'accent', 'ghost'], true) ? $style : 'primary';
        $size = $data['size'] ?? 'normal';
        $size = in_array($size, ['small', 'normal', 'large'], true) ? $size : 'normal';
        $class = 'cms-btn cms-btn-' . $style
            . ($size === 'large' ? ' cms-btn-lg' : ($size === 'small' ? ' cms-btn-sm' : ''));
        return '<a class="' . $class . '" href="' . e((string) ($data['url'] ?? '#')) . '">'
            . e((string) ($data['text'] ?? '')) . '</a>';
    }

    private static function video(array $data): string
    {
        $url = trim((string) ($data['url'] ?? ''));
        if ($url === '') {
            return '';
        }
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w\-]{6,})~', $url, $m)) {
            $embed = '<iframe src="https://www.youtube-nocookie.com/embed/' . e($m[1])
                . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>';
        } elseif (preg_match('~vimeo\.com/(\d+)~', $url, $m)) {
            $embed = '<iframe src="https://player.vimeo.com/video/' . e($m[1])
                . '" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe>';
        } elseif (preg_match('~\.(mp4|webm|ogg)(\?.*)?$~i', $url)) {
            $embed = '<video controls src="' . e($url) . '"></video>';
        } else {
            return '<!-- Video-URL nicht erkannt -->';
        }
        return '<div class="cms-video">' . $embed . '</div>';
    }

    private static function quote(array $data): string
    {
        $html = '<blockquote class="cms-quote' . self::variant($data) . '"><p>'
            . nl2br(e((string) ($data['text'] ?? ''))) . '</p>';
        if (!empty($data['author'])) {
            $html .= '<cite>' . e((string) $data['author']) . '</cite>';
        }
        return $html . '</blockquote>';
    }

    private static function accordion(array $data): string
    {
        $items = self::items($data, 'items');
        if ($items === []) {
            return '';
        }
        $html = '<div class="cms-accordion' . self::variant($data) . '">';
        foreach ($items as $i => $item) {
            $open = $i === 0 && !empty($data['first_open']) ? ' open' : '';
            $html .= '<details' . $open . '><summary>' . e((string) ($item['title'] ?? '')) . '</summary>'
                . '<div class="cms-acc-body">' . (string) ($item['text'] ?? '') . '</div></details>';
        }
        return $html . '</div>';
    }

    private static function form(array $block, array $data): string
    {
        $fid = preg_replace('/[^a-z0-9\-]/i', '', (string) ($block['id'] ?? 'form')) ?: 'form';
        $html = '<div class="cms-formwrap" id="f-' . $fid . '">';

        if (($_GET['sent'] ?? '') === $fid) {
            $success = (string) ($data['success'] ?? 'Vielen Dank! Deine Nachricht wurde gesendet.');
            return $html . '<div class="cms-form-note is-success">' . e($success) . '</div></div>';
        }
        if (($_GET['formerror'] ?? '') === $fid) {
            $html .= '<div class="cms-form-note is-error">Das hat leider nicht geklappt. Bitte alle Pflichtfelder korrekt ausfüllen und erneut versuchen.</div>';
        }

        $html .= '<form class="cms-form" method="post" action="' . e(url('/form/submit')) . '">';
        $html .= csrf_field();
        $html .= '<input type="hidden" name="form_page" value="' . (int) (self::$pageId ?? 0) . '">';
        $html .= '<input type="hidden" name="form_block" value="' . e($fid) . '">';
        // Honeypot gegen Spam-Bots – für Menschen unsichtbar.
        $html .= '<input class="cms-hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">';

        if (!isset($data['show_name']) || !empty($data['show_name'])) {
            $html .= '<label>Name*<input type="text" name="name" required></label>';
        }
        $html .= '<label>E-Mail*<input type="email" name="email" required></label>';
        if (!empty($data['show_phone'])) {
            $html .= '<label>Telefon<input type="text" name="phone"></label>';
        }
        // Eigene Felder aus dem Formular-Baukasten.
        foreach (self::items($data, 'fields') as $index => $field) {
            $label = trim((string) ($field['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $required = !empty($field['required']);
            $labelHtml = e($label) . ($required ? '*' : '');
            $name = 'custom[' . (int) $index . ']';
            $html .= '<label>' . $labelHtml;
            $html .= match ($field['type'] ?? 'text') {
                'textarea' => '<textarea name="' . $name . '" rows="4"' . ($required ? ' required' : '') . '></textarea>',
                'select' => '<select name="' . $name . '"' . ($required ? ' required' : '') . '><option value="">Bitte wählen …</option>'
                    . implode('', array_map(
                        static fn (string $option): string => '<option>' . e(trim($option)) . '</option>',
                        array_filter(explode(',', (string) ($field['options'] ?? '')))
                    )) . '</select>',
                'checkbox' => '<span class="cms-form-check"><input type="checkbox" name="' . $name . '" value="Ja"' . ($required ? ' required' : '') . '></span>',
                default => '<input type="text" name="' . $name . '"' . ($required ? ' required' : '') . '>',
            };
            $html .= '</label>';
        }

        $html .= '<label>Nachricht*<textarea name="message" rows="6" required></textarea></label>';
        $html .= '<button type="submit" class="cms-btn cms-btn-primary">' . e((string) ($data['button_text'] ?? 'Nachricht senden')) . '</button>';
        return $html . '</form></div>';
    }

    private static function lBrand(array $data): string
    {
        $html = '<a class="bwl-brand" href="{{base_url}}/">';
        if (!empty($data['logo'])) {
            $html .= '<img src="' . e((string) $data['logo']) . '" alt="">';
        }
        if (!isset($data['show_name']) || !empty($data['show_name'])) {
            $html .= '<span>{{site_name}}</span>';
        }
        return $html . '</a>';
    }

    /**
     * Menü-Block des Layout-Baukastens – komplett visuell konfigurierbar:
     * Vorlage (Dropdown/Mega/…), Ausrichtung, Schriftgröße, Abstände,
     * Farben des Aufklapp-Panels, Mega-Menü in voller Breite sowie der
     * Breakpoint, ab dem automatisch das mobile Burger-Menü erscheint.
     * Die Werte landen als CSS-Variablen am <nav>; Mobil-/Touch-Logik
     * übernimmt cms-blocks.js über data-nav / data-breakpoint.
     */
    private static function lMenu(array $data): string
    {
        $variant = in_array($data['variant'] ?? 'dropdown', ['dropdown', 'mega', 'vertical', 'simple'], true)
            ? $data['variant'] : 'dropdown';
        $align = in_array($data['align'] ?? 'left', ['left', 'center', 'right'], true)
            ? $data['align'] : 'left';

        $vars = '';
        $fontSize = (int) ($data['font_size'] ?? 0);
        if ($fontSize >= 10 && $fontSize <= 40) {
            $vars .= '--nav-fs:' . $fontSize . 'px;';
        }
        $padding = $data['item_padding'] ?? '';
        if ($padding !== '' && (int) $padding >= 0 && (int) $padding <= 60) {
            $vars .= '--nav-pad:' . (int) $padding . 'px;';
        }
        $gap = $data['gap'] ?? '';
        if ($gap !== '' && (int) $gap >= 0 && (int) $gap <= 60) {
            $vars .= '--nav-gap:' . (int) $gap . 'px;';
        }
        if (($data['transform'] ?? '') === 'uppercase') {
            $vars .= '--nav-tt:uppercase;--nav-ls:1px;';
        }
        foreach (['color' => '--nav-color', 'dropdown_bg' => '--nav-dd-bg', 'dropdown_text' => '--nav-dd-text'] as $key => $var) {
            $value = (string) ($data[$key] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $vars .= $var . ':' . strtolower($value) . ';';
            }
        }

        $breakpoint = (int) ($data['breakpoint'] ?? 900);
        $breakpoint = max(0, min(2000, $breakpoint));

        $classes = 't-nav bwl-menu cms-nav is-' . $align;
        if ($variant === 'mega' && !empty($data['mega_full'])) {
            $classes .= ' is-mega-full';
        }

        return '<nav class="' . $classes . '" data-nav data-breakpoint="' . $breakpoint . '"'
            . ($vars !== '' ? ' style="' . $vars . '"' : '') . '>'
            . '<button type="button" class="cms-nav-toggle" aria-label="Menü öffnen" aria-expanded="false">'
            . '<span></span><span></span><span></span></button>'
            . '{{menu:' . $variant . '}}</nav>';
    }

    private static function searchForm(array $data): string
    {
        return '<form class="cms-search" method="get" action="' . e(url('/suche')) . '">'
            . '<input type="search" name="q" placeholder="' . e((string) ($data['placeholder'] ?? 'Suchbegriff …')) . '" value="' . e((string) ($_GET['q'] ?? '')) . '">'
            . '<button type="submit" class="cms-btn cms-btn-primary">' . e((string) ($data['button_text'] ?? 'Suchen')) . '</button></form>';
    }

    private static int $globalDepth = 0;

    private static function globalBlock(array $data): string
    {
        $pageId = (int) ($data['page_id'] ?? 0);
        if ($pageId <= 0 || self::$globalDepth >= 3) {
            return '';
        }
        $page = \Models\Page::find($pageId);
        if ($page === null || !(int) $page['is_global'] || $page['deleted_at'] !== null) {
            return '<!-- Globaler Block nicht gefunden -->';
        }
        self::$globalDepth++;
        $html = '<div class="cms-globalblock">' . (new Renderer())->renderContent($page['content'] ?? null) . '</div>';
        self::$globalDepth--;
        return $html;
    }

    private static function map(array $data): string
    {
        $lat = max(-85.0, min(85.0, (float) ($data['lat'] ?? 51.1634)));
        $lon = max(-180.0, min(180.0, (float) ($data['lon'] ?? 10.4477)));
        $zoom = max(2, min(19, (int) ($data['zoom'] ?? 14)));
        $height = max(200, min(800, (int) ($data['height'] ?? 380)));

        $span = 360 / (2 ** $zoom);
        $bbox = sprintf('%F,%F,%F,%F', $lon - $span, $lat - $span / 2.2, $lon + $span, $lat + $span / 2.2);
        $src = 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode($bbox)
            . '&layer=mapnik&marker=' . rawurlencode($lat . ',' . $lon);

        return '<div class="cms-map" style="height:' . $height . 'px">'
            . '<iframe src="' . e($src) . '" loading="lazy" title="Karte"></iframe>'
            . '<a class="cms-map-link" href="' . e('https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lon . '#map=' . $zoom . '/' . $lat . '/' . $lon) . '" target="_blank" rel="noopener">Größere Karte öffnen ↗</a></div>';
    }

    private static function team(array $data): string
    {
        $members = self::items($data, 'members');
        if ($members === []) {
            return '';
        }
        $cols = min(4, max(1, (int) ($data['columns'] ?? 3)));
        $html = '<div class="cms-team" style="--cols:' . $cols . '">';
        foreach ($members as $member) {
            $html .= '<div class="cms-team-card">';
            if (!empty($member['src'])) {
                $html .= '<img src="' . e((string) $member['src']) . '" alt="' . e((string) ($member['name'] ?? '')) . '" loading="lazy">';
            }
            $html .= '<h3>' . e((string) ($member['name'] ?? '')) . '</h3>';
            if (!empty($member['role'])) {
                $html .= '<div class="cms-team-role">' . e((string) $member['role']) . '</div>';
            }
            if (!empty($member['text'])) {
                $html .= '<p>' . e((string) $member['text']) . '</p>';
            }
            $html .= '</div>';
        }
        return $html . '</div>';
    }

    private static function pricing(array $data): string
    {
        $plans = self::items($data, 'plans');
        if ($plans === []) {
            return '';
        }
        $html = '<div class="cms-pricing" style="--cols:' . min(4, max(1, count($plans))) . '">';
        foreach ($plans as $plan) {
            $html .= '<div class="cms-price-card' . (!empty($plan['highlight']) ? ' is-highlight' : '') . '">';
            $html .= '<h3>' . e((string) ($plan['title'] ?? '')) . '</h3>';
            $html .= '<div class="cms-price">' . e((string) ($plan['price'] ?? ''));
            if (!empty($plan['period'])) {
                $html .= '<span>/ ' . e((string) $plan['period']) . '</span>';
            }
            $html .= '</div>';
            $features = array_filter(array_map('trim', explode("\n", (string) ($plan['features'] ?? ''))));
            if ($features !== []) {
                $html .= '<ul>';
                foreach ($features as $feature) {
                    $html .= '<li>' . e($feature) . '</li>';
                }
                $html .= '</ul>';
            }
            if (!empty($plan['button_text'])) {
                $html .= '<a class="cms-btn ' . (!empty($plan['highlight']) ? 'cms-btn-primary' : 'cms-btn-outline') . '" href="'
                    . e((string) ($plan['button_url'] ?? '#')) . '">' . e((string) $plan['button_text']) . '</a>';
            }
            $html .= '</div>';
        }
        return $html . '</div>';
    }

    private static function countdown(array $data): string
    {
        $target = trim((string) ($data['target'] ?? ''));
        $ts = $target !== '' ? strtotime($target) : false;
        if ($ts === false) {
            return '<!-- Countdown: kein gültiges Zieldatum -->';
        }
        $html = '<div class="cms-countdown" data-countdown="' . e(date('c', $ts)) . '" data-expired="' . e((string) ($data['expired_text'] ?? 'Es ist so weit!')) . '">';
        if (!empty($data['title'])) {
            $html .= '<div class="cms-cd-title">' . e((string) $data['title']) . '</div>';
        }
        $html .= '<div class="cms-cd-grid">';
        foreach (['d' => 'Tage', 'h' => 'Stunden', 'm' => 'Minuten', 's' => 'Sekunden'] as $key => $label) {
            $html .= '<div class="cms-cd-cell"><b data-cd="' . $key . '">–</b><span>' . $label . '</span></div>';
        }
        return $html . '</div></div>';
    }

    private static function social(array $data): string
    {
        $networks = [
            'facebook' => ['f', '#1877f2'], 'instagram' => ['IG', '#e4405f'], 'x' => ['𝕏', '#111111'],
            'youtube' => ['▶', '#ff0000'], 'linkedin' => ['in', '#0a66c2'], 'tiktok' => ['TT', '#111111'],
            'whatsapp' => ['WA', '#25d366'], 'mail' => ['✉', '#64748b'], 'phone' => ['☎', '#16a34a'],
        ];
        $links = self::items($data, 'links');
        if ($links === []) {
            return '';
        }
        $size = in_array($data['size'] ?? 'normal', ['small', 'normal', 'large'], true) ? $data['size'] : 'normal';
        $html = '<div class="cms-social is-' . $size . '">';
        foreach ($links as $link) {
            $network = (string) ($link['network'] ?? '');
            $url = (string) ($link['url'] ?? '');
            if (!isset($networks[$network]) || $url === '') {
                continue;
            }
            [$label, $color] = $networks[$network];
            $html .= '<a href="' . e($url) . '" target="_blank" rel="noopener" aria-label="' . e(ucfirst($network))
                . '" style="--sc:' . $color . '"><span>' . $label . '</span></a>';
        }
        return $html . '</div>';
    }

    private static function news(array $data): string
    {
        $posts = Post::latestNews(min(24, max(1, (int) ($data['count'] ?? 3))));
        if ($posts === []) {
            return '<p class="cms-empty">Zurzeit keine News.</p>';
        }
        return self::postCards($posts, $data, 'news');
    }

    private static function events(array $data): string
    {
        $posts = Post::upcomingEvents(min(24, max(1, (int) ($data['count'] ?? 3))));
        if ($posts === []) {
            return '<p class="cms-empty">Zurzeit keine anstehenden Termine.</p>';
        }
        return self::postCards($posts, $data, 'events');
    }

    /** @param array<int, array<string, mixed>> $posts */
    private static function postCards(array $posts, array $data, string $type): string
    {
        $cols = min(4, max(1, (int) ($data['columns'] ?? 3)));
        $mode = $data['layout'] ?? 'cards';
        $mode = in_array($mode, ['cards', 'list', 'minimal'], true) ? $mode : 'cards';
        $showImage = !isset($data['show_image']) || !empty($data['show_image']);
        $showDate = !isset($data['show_date']) || !empty($data['show_date']);
        $showExcerpt = !isset($data['show_excerpt']) || !empty($data['show_excerpt']);
        $showLocation = !empty($data['show_location']);

        $html = '<div class="cms-cards is-' . $mode . '" style="--cols:' . ($mode === 'cards' ? $cols : 1) . '">';
        foreach ($posts as $post) {
            $link = url('/' . $type . '/' . $post['slug']);
            $html .= '<article class="cms-card">';
            if ($mode === 'cards' && $showImage && !empty($post['image'])) {
                $html .= '<a class="cms-card-img" href="' . e($link) . '"><img src="' . e((string) $post['image'])
                    . '" alt="' . e((string) $post['title']) . '" loading="lazy"></a>';
            }
            $html .= '<div class="cms-card-body">';
            if ($showDate) {
                $date = $type === 'events'
                    ? format_date_de($post['start_at'] ?? null, true)
                    : format_date_de($post['published_at'] ?? $post['created_at'] ?? null);
                if ($date !== '') {
                    $html .= '<div class="cms-card-date">' . e($date) . '</div>';
                }
            }
            $html .= '<h3><a href="' . e($link) . '">' . e((string) $post['title']) . '</a></h3>';
            if ($type === 'events' && $showLocation && !empty($post['location'])) {
                $html .= '<div class="cms-card-location">📍 ' . e((string) $post['location']) . '</div>';
            }
            if ($mode !== 'minimal' && $showExcerpt && !empty($post['excerpt'])) {
                $html .= '<p>' . e((string) $post['excerpt']) . '</p>';
            }
            $html .= '</div></article>';
        }
        return $html . '</div>';
    }
}
