<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Models\FormEntry;

class FormEntriesController extends AdminController
{
    public function index(): void
    {
        $entries = FormEntry::all();
        FormEntry::markAllRead();
        $this->view('admin/forms/index', [
            'title' => 'Formular-Einsendungen',
            'active' => 'forms',
            'entries' => $entries,
        ]);
    }

    public function delete(string $id): void
    {
        FormEntry::delete((int) $id);
        flash('success', 'Einsendung gelöscht.');
        redirect('/admin/forms');
    }
}
