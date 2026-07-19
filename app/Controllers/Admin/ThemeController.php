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
        if (!Themes::isCustom($key)) {
            flash('error', 'Mitgelieferte Designs können nicht gelöscht werden.');
            redirect('/admin/themes');
        }
        $wasActive = Themes::activeKey() === $key;
        Themes::deleteCustom($key);
        // War es das aktive Design, sauber auf das Hausdesign zurückschalten.
        if ($wasActive) {
            Themes::apply('blockwerk');
            flash('success', 'Eigenes Design gelöscht. Es war aktiv – daher wurde „Blockwerk Orange" aktiviert.');
        } else {
            flash('success', 'Eigenes Design gelöscht.');
        }
        redirect('/admin/themes');
    }
}
