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
        $this->form(null);
    }

    public function edit(string $id): void
    {
        $layout = Layout::find((int) $id) ?? $this->abort();
        $this->form($layout);
    }

    private function form(?array $layout): void
    {
        $design = json_decode((string) ($layout['design'] ?? ''), true) ?: [];
        $this->view('admin/layouts/form', [
            'title' => $layout ? 'Layout bearbeiten' : 'Neues Layout',
            'active' => 'layouts',
            'layout' => $layout,
            'design' => $design,
            'fonts' => \Models\Font::all(),
        ]);
    }

    public function store(): void
    {
        [$name, $html] = $this->validated('/admin/layouts/new');
        Layout::create($name, $html, $this->designJson());
        flash('success', 'Layout angelegt.');
        redirect('/admin/layouts');
    }

    public function update(string $id): void
    {
        $layout = Layout::find((int) $id) ?? $this->abort();
        [$name, $html] = $this->validated('/admin/layouts/' . $layout['id'] . '/edit');
        Layout::update((int) $layout['id'], $name, $html, $this->designJson());
        flash('success', 'Layout gespeichert.');
        redirect('/admin/layouts');
    }

    /** Farben (Color-Picker) und Schriften aus dem Formular als JSON validieren. */
    private function designJson(): ?string
    {
        $input = $_POST['design'] ?? [];
        if (!is_array($input)) {
            return null;
        }
        $design = ['colors' => [], 'fonts' => []];
        foreach (['primary', 'accent', 'text', 'bg', 'surface'] as $key) {
            $value = (string) ($input['colors'][$key] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $design['colors'][$key] = strtolower($value);
            }
        }
        foreach (['heading', 'body'] as $key) {
            $id = (int) ($input['fonts'][$key] ?? 0);
            if ($id > 0) {
                $design['fonts'][$key] = $id;
            }
        }
        if (is_string($input['css'] ?? null) && trim($input['css']) !== '') {
            $design['css'] = substr($input['css'], 0, 100000);
        }
        return json_encode($design, JSON_UNESCAPED_UNICODE) ?: null;
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
