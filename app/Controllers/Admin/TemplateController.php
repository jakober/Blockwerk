<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Models\Template;

class TemplateController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    public function index(): void
    {
        $this->view('admin/templates/index', [
            'title' => 'Templates',
            'active' => 'templates',
            'templates' => Template::all(),
        ]);
    }

    public function create(): void
    {
        $this->view('admin/templates/form', [
            'title' => 'Neues Template',
            'active' => 'templates',
            'template' => null,
        ]);
    }

    public function edit(string $id): void
    {
        $template = Template::find((int) $id) ?? $this->abort();
        $this->view('admin/templates/form', [
            'title' => 'Template bearbeiten',
            'active' => 'templates',
            'template' => $template,
        ]);
    }

    public function store(): void
    {
        [$name, $key, $html] = $this->validated('/admin/templates/new');
        if (Template::findByKey($key) !== null) {
            flash('error', 'Der Schlüssel "' . $key . '" ist bereits vergeben.');
            redirect('/admin/templates/new');
        }
        Template::create($name, $key, $html);
        flash('success', 'Template angelegt.');
        redirect('/admin/templates');
    }

    public function update(string $id): void
    {
        $template = Template::find((int) $id) ?? $this->abort();
        [$name, $key, $html] = $this->validated('/admin/templates/' . $template['id'] . '/edit');
        $existing = Template::findByKey($key);
        if ($existing !== null && (int) $existing['id'] !== (int) $template['id']) {
            flash('error', 'Der Schlüssel "' . $key . '" ist bereits vergeben.');
            redirect('/admin/templates/' . $template['id'] . '/edit');
        }
        Template::update((int) $template['id'], $name, $key, $html);
        flash('success', 'Template gespeichert.');
        redirect('/admin/templates');
    }

    public function delete(string $id): void
    {
        Template::delete((int) $id);
        flash('success', 'Template gelöscht.');
        redirect('/admin/templates');
    }

    /** @return array{0:string,1:string,2:string} */
    private function validated(string $backTo): array
    {
        $name = trim($_POST['name'] ?? '');
        $key = slugify(trim($_POST['tkey'] ?? '') ?: $name);
        $html = (string) ($_POST['html'] ?? '');
        if ($name === '' || $key === '' || trim($html) === '') {
            flash('error', 'Bitte Name, Schlüssel und HTML angeben.');
            redirect($backTo);
        }
        return [$name, $key, $html];
    }

    private function abort(): never
    {
        flash('error', 'Template nicht gefunden.');
        redirect('/admin/templates');
    }
}
