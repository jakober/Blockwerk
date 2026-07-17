<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\BlockRegistry;
use Models\Layout;
use Models\Page;

class PageController extends AdminController
{
    public function index(): void
    {
        $layouts = [];
        foreach (Layout::all() as $layout) {
            $layouts[(int) $layout['id']] = $layout['name'];
        }

        $this->view('admin/pages/index', [
            'title' => 'Seiten',
            'active' => 'pages',
            'pages' => Page::tree(),
            'layouts' => $layouts,
        ]);
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(string $id): void
    {
        $page = Page::find((int) $id) ?? $this->abort();
        $this->form($page);
    }

    public function store(): void
    {
        $data = $this->validated();
        $id = Page::create($data);
        flash('success', 'Seite angelegt. Jetzt Inhalte hinzufügen!');
        redirect('/admin/pages/' . $id . '/editor');
    }

    public function update(string $id): void
    {
        $page = Page::find((int) $id) ?? $this->abort();
        Page::update((int) $page['id'], $this->validated((int) $page['id']));
        flash('success', 'Seite gespeichert.');
        redirect('/admin/pages');
    }

    public function delete(string $id): void
    {
        Page::delete((int) $id);
        flash('success', 'Seite gelöscht.');
        redirect('/admin/pages');
    }

    public function editor(string $id): void
    {
        $page = Page::find((int) $id) ?? $this->abort();
        $content = json_decode((string) ($page['content'] ?? ''), true);
        if (!is_array($content)) {
            $content = [];
        }
        if (!is_array($content['rows'] ?? null)) {
            $content['rows'] = [];
        }

        $layout = $page['layout_id'] ? Layout::find((int) $page['layout_id']) : null;
        $layout ??= Layout::first();

        $this->view('admin/pages/editor', [
            'title' => 'Inhalt: ' . $page['title'],
            'active' => 'pages',
            'page' => $page,
            'contentJson' => json_encode($content, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE),
            'designHead' => (new \Core\Renderer())->designHead($layout),
            'bodyClass' => 'is-editor',
        ]);
    }

    public function saveContent(string $id): void
    {
        $page = Page::find((int) $id);
        header('Content-Type: application/json');
        if ($page === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Seite nicht gefunden.']);
            return;
        }

        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Ungültige Daten.']);
            return;
        }

        $clean = $this->sanitizeContent($data);
        Page::saveContent((int) $page['id'], json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"rows":[]}');
        echo json_encode(['ok' => true]);
    }

    /** Nur bekannte Struktur und Block-Typen übernehmen. */
    private function sanitizeContent(array $data): array
    {
        $rows = [];
        foreach (($data['rows'] ?? []) as $row) {
            if (!is_array($row) || !is_array($row['columns'] ?? null)) {
                continue;
            }
            $columns = [];
            foreach ($row['columns'] as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $blocks = [];
                foreach (($column['blocks'] ?? []) as $block) {
                    if (!is_array($block) || !in_array($block['type'] ?? '', BlockRegistry::types(), true)) {
                        continue;
                    }
                    $blocks[] = [
                        'id' => substr((string) ($block['id'] ?? uniqid('b-')), 0, 40),
                        'type' => $block['type'],
                        'data' => BlockRegistry::sanitizeData((array) ($block['data'] ?? [])),
                    ];
                }
                $columns[] = [
                    'id' => substr((string) ($column['id'] ?? uniqid('col-')), 0, 40),
                    'span' => min(12, max(1, (int) ($column['span'] ?? 12))),
                    'blocks' => $blocks,
                ];
            }
            if ($columns !== []) {
                $rowOut = [
                    'id' => substr((string) ($row['id'] ?? uniqid('row-')), 0, 40),
                    'columns' => $columns,
                ];
                $rowStyle = [];
                foreach ((array) ($row['style'] ?? []) as $styleKey => $styleValue) {
                    if (is_scalar($styleValue)) {
                        $rowStyle[(string) $styleKey] = is_bool($styleValue) ? (int) $styleValue : $styleValue;
                    }
                }
                if ($rowStyle !== []) {
                    $rowOut['style'] = $rowStyle;
                }
                $rows[] = $rowOut;
            }
        }

        $clean = ['rows' => $rows];
        if (is_string($data['css'] ?? null) && trim($data['css']) !== '') {
            $clean['css'] = substr($data['css'], 0, 100000);
        }
        return $clean;
    }

    private function form(?array $page): void
    {
        $this->view('admin/pages/form', [
            'title' => $page ? 'Seite bearbeiten' : 'Neue Seite',
            'active' => 'pages',
            'page' => $page,
            'layouts' => Layout::all(),
            'parents' => array_filter(Page::tree(), fn (array $p) => $page === null || (int) $p['id'] !== (int) $page['id']),
        ]);
    }

    private function validated(?int $ignoreId = null): array
    {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect($ignoreId ? '/admin/pages/' . $ignoreId . '/edit' : '/admin/pages/new');
        }

        $slug = slugify(trim($_POST['slug'] ?? '') ?: $title);
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $layoutId = (int) ($_POST['layout_id'] ?? 0);

        return [
            'title' => $title,
            'slug' => $slug,
            'parent_id' => $parentId > 0 && $parentId !== $ignoreId ? $parentId : null,
            'layout_id' => $layoutId > 0 ? $layoutId : null,
            'in_menu' => isset($_POST['in_menu']) ? 1 : 0,
            'menu_order' => (int) ($_POST['menu_order'] ?? 0),
            'published' => isset($_POST['published']) ? 1 : 0,
        ];
    }

    private function abort(): never
    {
        flash('error', 'Seite nicht gefunden.');
        redirect('/admin/pages');
    }
}
