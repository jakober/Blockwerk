<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Models\Page;

/**
 * Globale Blöcke: wiederverwendbare Inhaltsbereiche, die als Spezial-Seiten
 * gespeichert und mit dem normalen WYSIWYG-Editor bearbeitet werden.
 * Im Seiten-Editor per Block "Globaler Block" einsetzbar – eine Änderung
 * wirkt überall.
 */
class GlobalBlockController extends AdminController
{
    public function index(): void
    {
        $this->view('admin/globals/index', [
            'title' => 'Globale Blöcke',
            'active' => 'globals',
            'globals' => Page::globals(),
        ]);
    }

    public function store(): void
    {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Bitte einen Namen angeben.');
            redirect('/admin/globals');
        }
        $id = Page::create([
            'parent_id' => null,
            'title' => $title,
            'slug' => 'global-' . slugify($title),
            'layout_id' => null,
            'in_menu' => 0,
            'menu_order' => 0,
            'published' => 0,
            'lang' => cms_default_lang(),
            'is_global' => 1,
        ]);
        flash('success', 'Globaler Block angelegt – jetzt Inhalte hinzufügen.');
        redirect('/admin/pages/' . $id . '/editor');
    }
}
