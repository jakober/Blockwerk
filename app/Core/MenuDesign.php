<?php
declare(strict_types=1);

namespace Core;

use Models\Layout;
use Models\Setting;
use Models\Template;

/**
 * Zentrale Logik des Menü-Designers: Einstellungen laden/speichern und
 * das generierte Menü-HTML überall hinterlegen (main-menu-Template für
 * klassische Layouts, l-menu-Blöcke in visuell gebauten Layouts).
 */
class MenuDesign
{
    public const DEFAULTS = [
        'variant' => 'dropdown',
        'align' => 'left',
        'font_size' => 16,
        'item_padding' => 14,
        'gap' => 4,
        'transform' => 'normal',
        'color' => '',
        'dropdown_bg' => '',
        'dropdown_text' => '',
        'mega_full' => 0,
        'breakpoint' => 900,
    ];

    public static function stored(): array
    {
        $stored = json_decode(Setting::get('menu_design', ''), true);
        if (!is_array($stored)) {
            return self::DEFAULTS;
        }
        return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }

    public static function save(array $design): void
    {
        Setting::set('menu_design', (string) json_encode($design));
        self::apply($design);
    }

    public static function reset(): void
    {
        Setting::set('menu_design', '');
        self::apply(self::DEFAULTS);
    }

    /**
     * Läuft einmalig nach einem Update: Bringt alte, von Hand angelegte
     * Menü-Templates (ohne mobiles Burger-Menü) auf den Designer-Stand,
     * damit das mobile Menü auf jeder Installation funktioniert.
     */
    public static function migrate(): void
    {
        $template = Template::findByKey('main-menu');
        if ($template !== null && str_contains((string) $template['html'], 'data-nav')) {
            return; // bereits vom Designer generiert
        }
        self::apply(self::stored());
    }

    public static function apply(array $design): void
    {
        $html = BlockRegistry::menuHtml($design);

        $template = Template::findByKey('main-menu');
        if ($template !== null) {
            Template::update((int) $template['id'], $template['name'], 'main-menu', $html);
        } else {
            Template::create('Hauptmenü', 'main-menu', $html);
        }

        foreach (Layout::all() as $layout) {
            $builder = json_decode((string) ($layout['builder'] ?? ''), true);
            if (!is_array($builder) || !is_array($builder['rows'] ?? null)) {
                continue;
            }
            $changed = false;
            foreach ($builder['rows'] as $ri => $row) {
                foreach ($row['columns'] ?? [] as $ci => $column) {
                    foreach ($column['blocks'] ?? [] as $bi => $block) {
                        if (($block['type'] ?? '') === 'l-menu') {
                            $builder['rows'][$ri]['columns'][$ci]['blocks'][$bi]['data'] = $design;
                            $changed = true;
                        }
                    }
                }
            }
            if ($changed) {
                Layout::saveBuilder((int) $layout['id'], (string) json_encode($builder));
            }
        }
    }
}
