<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Models\Layout;

class LayoutController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

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

    /* ---------- Visueller Layout-Baukasten ---------- */

    /** Neues Layout direkt im visuellen Modus anlegen. */
    public function visualNew(): void
    {
        $id = Layout::create('Neues Layout (visuell)', '<!doctype html><html lang="de"><head><meta charset="utf-8"><title>{{title}}</title></head><body>{{content}}</body></html>');
        Layout::saveBuilder($id, json_encode(self::defaultBuilder(), JSON_UNESCAPED_UNICODE));
        flash('success', 'Visuelles Layout angelegt – Kopfzeile, Inhaltsbereich und Fußzeile kannst du jetzt frei per Drag & Drop gestalten.');
        redirect('/admin/layouts/' . $id . '/builder');
    }

    public function builder(string $id): void
    {
        $layout = Layout::find((int) $id) ?? $this->abort();
        $builder = json_decode((string) ($layout['builder'] ?? ''), true);
        if (!is_array($builder) || !is_array($builder['rows'] ?? null)) {
            $builder = self::defaultBuilder();
        }

        \Core\View::render('admin/pages/editor', [
            'title' => 'Layout-Baukasten: ' . $layout['name'],
            'active' => 'layouts',
            'flash' => flash(),
            'editorTitle' => $layout['name'],
            'contentJson' => json_encode($builder, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE),
            'designHead' => (new \Core\Renderer())->designHead($layout),
            'globalBlocks' => array_map(
                static fn (array $g): array => [(string) $g['id'], $g['title']],
                \Models\Page::globals()
            ),
            'saveUrl' => url('/admin/layouts/' . $layout['id'] . '/builder-content'),
            'backUrl' => url('/admin/layouts'),
            'backLabel' => '← Layouts',
            'previewHref' => url('/'),
            'versionsUrl' => null,
            'mode' => 'layout',
            'bodyClass' => 'is-editor',
        ], 'admin/_shell');
    }

    public function saveBuilder(string $id): void
    {
        $layout = Layout::find((int) $id);
        header('Content-Type: application/json');
        if ($layout === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Layout nicht gefunden.']);
            return;
        }
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($data)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Ungültige Daten.']);
            return;
        }
        $clean = \Core\BlockRegistry::sanitizeTree($data);
        Layout::saveBuilder((int) $layout['id'], json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null);
        \Core\Cache::clear();
        echo json_encode(['ok' => true]);
    }

    /** Zurück zum HTML-Modus: Baukasten-Struktur verwerfen. */
    public function builderReset(string $id): void
    {
        Layout::saveBuilder((int) $id, null);
        flash('success', 'Visueller Modus deaktiviert – das Layout nutzt wieder sein HTML.');
        redirect('/admin/layouts');
    }

    /** Startstruktur: Kopfzeile (Logo + Menü), Inhaltsbereich, Fußzeile. */
    private static function defaultBuilder(): array
    {
        return ['rows' => [
            ['id' => 'lrow-header', 'style' => ['bg' => '#ffffff', 'pt' => 10, 'pb' => 10], 'columns' => [
                ['id' => 'lcol-brand', 'span' => 4, 'blocks' => [
                    ['id' => 'lb-brand', 'type' => 'l-brand', 'data' => ['logo' => '', 'show_name' => 1]],
                ]],
                ['id' => 'lcol-menu', 'span' => 8, 'blocks' => [
                    ['id' => 'lb-menu', 'type' => 'l-menu', 'data' => ['variant' => 'dropdown', 'align' => 'right']],
                ]],
            ]],
            ['id' => 'lrow-content', 'columns' => [
                ['id' => 'lcol-content', 'span' => 12, 'blocks' => [
                    ['id' => 'lb-content', 'type' => 'l-content', 'data' => []],
                ]],
            ]],
            ['id' => 'lrow-footer', 'style' => ['bg' => '#f1f5f9', 'pt' => 24, 'pb' => 24], 'columns' => [
                ['id' => 'lcol-footer', 'span' => 12, 'blocks' => [
                    ['id' => 'lb-footer', 'type' => 'text', 'data' => ['html' => '<p>© {{year}} {{site_name}}</p>']],
                ]],
            ]],
        ]];
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
