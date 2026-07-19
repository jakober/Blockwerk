<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Themes;

class ThemeController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    public function index(): void
    {
        $this->view('admin/themes/index', [
            'title' => 'Designs',
            'active' => 'themes',
            'themes' => Themes::all(),
            'activeKey' => Themes::activeKey(),
        ]);
    }

    public function apply(string $key): void
    {
        if (Themes::apply($key)) {
            $name = Themes::all()[$key]['name'];
            flash('success', 'Design "' . $name . '" ist jetzt aktiv. Alle Seiten mit dem Standard-Layout nutzen die neue Optik – die Inhalte sind unverändert.');
        } else {
            flash('error', 'Unbekanntes Design.');
        }
        redirect('/admin/themes');
    }

    public function delete(string $key): void
    {
        if (Themes::isCustom($key)) {
            Themes::deleteCustom($key);
            flash('success', 'Eigenes Design gelöscht.');
        } else {
            flash('error', 'Mitgelieferte Designs können nicht gelöscht werden.');
        }
        redirect('/admin/themes');
    }
}
