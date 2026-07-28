<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\BlockRegistry;
use Core\MenuDesign;
use Core\Renderer;
use Models\Layout;

/**
 * Visueller Menü-Designer: alle Einstellungen (Vorlage, Größen, Farben,
 * Breakpoint) werden per Formular gewählt – das Menü-Template
 * ({{template:main-menu}}) wird daraus im Hintergrund generiert und auch
 * in visuell gebauten Layouts (l-menu-Block) übernommen.
 */
class MenuController extends AdminController
{
    public function edit(): void
    {
        $this->requireAdmin();
        $design = MenuDesign::stored();
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
        $d = MenuDesign::DEFAULTS;
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

        MenuDesign::save($d);

        flash('success', 'Menü gespeichert – das Template wurde im Hintergrund aktualisiert und gilt für alle Layouts.');
        redirect('/admin/menu');
    }

    public function reset(): void
    {
        $this->requireAdmin();
        MenuDesign::reset();
        flash('success', 'Menü zurückgesetzt – Standardfarben und Standard-Vorlage sind wiederhergestellt.');
        redirect('/admin/menu');
    }
}
