<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Models\Layout;

class LayoutController extends AdminController
{
    public function index(): void
    {
        $this->view('admin/layouts/index', [
            'title' => 'Layouts',
            'active' => 'layouts',
            'layouts' => Layout::all(),
        ]);
    }

    public function create(): void
    {
        $this->view('admin/layouts/form', [
            'title' => 'Neues Layout',
            'active' => 'layouts',
            'layout' => null,
        ]);
    }

    public function edit(string $id): void
    {
        $layout = Layout::find((int) $id) ?? $this->abort();
        $this->view('admin/layouts/form', [
            'title' => 'Layout bearbeiten',
            'active' => 'layouts',
            'layout' => $layout,
        ]);
    }

    public function store(): void
    {
        [$name, $html] = $this->validated('/admin/layouts/new');
        Layout::create($name, $html);
        flash('success', 'Layout angelegt.');
        redirect('/admin/layouts');
    }

    public function update(string $id): void
    {
        $layout = Layout::find((int) $id) ?? $this->abort();
        [$name, $html] = $this->validated('/admin/layouts/' . $layout['id'] . '/edit');
        Layout::update((int) $layout['id'], $name, $html);
        flash('success', 'Layout gespeichert.');
        redirect('/admin/layouts');
    }

    public function delete(string $id): void
    {
        Layout::delete((int) $id);
        flash('success', 'Layout gelöscht. Betroffene Seiten nutzen jetzt das Standard-Layout.');
        redirect('/admin/layouts');
    }

    /** @return array{0:string,1:string} */
    private function validated(string $backTo): array
    {
        $name = trim($_POST['name'] ?? '');
        $html = (string) ($_POST['html'] ?? '');
        if ($name === '' || trim($html) === '') {
            flash('error', 'Bitte Name und HTML angeben.');
            redirect($backTo);
        }
        return [$name, $html];
    }

    private function abort(): never
    {
        flash('error', 'Layout nicht gefunden.');
        redirect('/admin/layouts');
    }
}
