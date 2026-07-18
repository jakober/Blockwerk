<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\BlockRegistry;
use Core\Renderer;
use Models\Layout;
use Models\Setting;
use Models\Template;

/**
 * Visueller Menü-Designer: alle Einstellungen (Vorlage, Größen, Farben,
 * Breakpoint) werden per Formular gewählt – das Menü-Template
 * ({{template:main-menu}}) wird daraus im Hintergrund generiert und auch
 * in visuell gebauten Layouts (l-menu-Block) übernommen.
 */
class MenuController extends AdminController
{
    private const DEFAULTS = [
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

    public function edit(): void
    {
        $this->requireAdmin();
        $design = $this->design();
        $renderer = new Renderer();
        $layouts = Layout::all();

        $this->view('admin/menu/edit', [
            'title' => 'Menü gestalten',
            'active' => 'menu',
            'design' => $design,
            'previewHtml' => $renderer->fillForPreview(BlockRegistry::menuHtml($design)),
            'designHead' => $renderer->designHead($layouts[0] ?? null),
        ]);
    }

    public function save(): void
    {
        $this->requireAdmin();
        $d = self::DEFAULTS;
        $d['variant'] = in_array($_POST['variant'] ?? '', ['dropdown', 'mega', 'vertical', 'simple'], true)
            ? $_POST['variant'] : 'dropdown';
        $d['align'] = in_array($_POST['align'] ?? '', ['left', 'center', 'right'], true)
            ? $_POST['align'] : 'left';
        $d['font_size'] = max(10, min(40, (int) ($_POST['font_size'] ?? 16)));
        $d['item_padding'] = max(0, min(60, (int) ($_POST['item_padding'] ?? 14)));
        $d['gap'] = max(0, min(60, (int) ($_POST['gap'] ?? 4)));
        $d['transform'] = ($_POST['transform'] ?? '') === 'uppercase' ? 'uppercase' : 'normal';
        foreach (['color', 'dropdown_bg', 'dropdown_text'] as $key) {
            $value = trim((string) ($_POST[$key] ?? ''));
            $d[$key] = !empty($_POST[$key . '_use']) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)
                ? strtolower($value) : '';
        }
        $d['mega_full'] = empty($_POST['mega_full']) ? 0 : 1;
        $d['breakpoint'] = max(0, min(2000, (int) ($_POST['breakpoint'] ?? 900)));

        Setting::set('menu_design', (string) json_encode($d));
        $this->applyEverywhere($d);

        flash('success', 'Menü gespeichert – das Template wurde im Hintergrund aktualisiert und gilt für alle Layouts.');
        redirect('/admin/menu');
    }

    private function design(): array
    {
        $stored = json_decode(Setting::get('menu_design', ''), true);
        if (!is_array($stored)) {
            return self::DEFAULTS;
        }
        return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }

    /**
     * Generiertes Menü-HTML überall hinterlegen: ins main-menu-Template
     * (klassische Layouts) und in alle l-menu-Blöcke visueller Layouts.
     */
    private function applyEverywhere(array $design): void
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
