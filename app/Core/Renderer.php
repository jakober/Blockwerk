<?php
declare(strict_types=1);

namespace Core;

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
 */
class Renderer
{
    private const MAX_TEMPLATE_DEPTH = 5;

    public function renderPage(array $page): string
    {
        $layout = $page['layout_id'] ? Layout::find((int) $page['layout_id']) : null;
        $layout ??= Layout::first();
        $layoutHtml = $layout['html'] ?? "<!doctype html>\n<html lang=\"de\"><head><meta charset=\"utf-8\"><title>{{title}}</title></head><body>{{content}}</body></html>";

        $contentHtml = $this->renderContent($page['content'] ?? null);

        return $this->replacePlaceholders($layoutHtml, [
            'content' => $contentHtml,
            'title' => $page['title'] ?? '',
        ]);
    }

    public function renderContent(?string $json): string
    {
        $data = json_decode((string) $json, true);
        if (!is_array($data) || !is_array($data['rows'] ?? null)) {
            return '';
        }

        $html = '';
        foreach ($data['rows'] as $row) {
            if (!is_array($row['columns'] ?? null)) {
                continue;
            }
            $html .= '<div class="cms-row">';
            foreach ($row['columns'] as $column) {
                $span = min(12, max(1, (int) ($column['span'] ?? 12)));
                $html .= '<div class="cms-col" style="--span:' . $span . '">';
                foreach (($column['blocks'] ?? []) as $block) {
                    if (is_array($block)) {
                        $html .= BlockRegistry::render($block);
                    }
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        return $html;
    }

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

        return strtr($html, [
            '{{content}}' => $vars['content'] ?? '',
            '{{title}}' => e($vars['title'] ?? ''),
            '{{site_name}}' => e(Setting::get('site_name', 'Meine Website')),
            '{{base_url}}' => App::base(),
            '{{year}}' => date('Y'),
            '{{menu}}' => $this->renderMenu(),
        ]);
    }

    private function renderMenu(): string
    {
        $pages = Page::menuPages();
        $byParent = [];
        foreach ($pages as $page) {
            $byParent[(int) ($page['parent_id'] ?? 0)][] = $page;
        }
        return $this->renderMenuLevel($byParent, 0, 'menu');
    }

    private function renderMenuLevel(array $byParent, int $parentId, string $class): string
    {
        if (empty($byParent[$parentId])) {
            return '';
        }
        $html = '<ul class="' . $class . '">';
        foreach ($byParent[$parentId] as $page) {
            $children = $this->renderMenuLevel($byParent, (int) $page['id'], 'submenu');
            $html .= '<li' . ($children !== '' ? ' class="has-children"' : '') . '>';
            $html .= '<a href="' . e(url('/' . $page['slug'])) . '">' . e($page['title']) . '</a>';
            $html .= $children . '</li>';
        }
        return $html . '</ul>';
    }
}
