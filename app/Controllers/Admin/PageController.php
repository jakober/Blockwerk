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
        // Neue Seiten hängen sich ans Ende ihrer Ebene (statt vorne einzureihen).
        $data['menu_order'] = Page::nextMenuOrder($data['parent_id']);
        $id = Page::create($data);
        flash('success', 'Seite angelegt. Jetzt Inhalte hinzufügen!');
        redirect('/admin/pages/' . $id . '/editor');
    }

    public function update(string $id): void
    {
        $page = Page::find((int) $id) ?? $this->abort();
        $data = $this->validated((int) $page['id']);
        Page::update((int) $page['id'], $data);

        // Slug geändert? Automatische 301-Weiterleitung von der alten Adresse.
        $newPage = Page::find((int) $page['id']);
        if ($newPage !== null && $newPage['slug'] !== $page['slug'] && !(int) $page['is_global']) {
            \Models\Redirect::set($page['slug'], $newPage['slug']);
        }

        flash('success', 'Seite gespeichert.');
        redirect('/admin/pages');
    }

    public function delete(string $id): void
    {
        $page = Page::find((int) $id);
        Page::softDelete((int) $id);
        flash('success', 'In den Papierkorb verschoben. Wiederherstellen unter Seiten → Papierkorb.');
        redirect(!empty($page['is_global']) ? '/admin/globals' : '/admin/pages');
    }

    public function trash(): void
    {
        $this->view('admin/pages/trash', [
            'title' => 'Papierkorb',
            'active' => 'pages',
            'pages' => Page::trashed(),
        ]);
    }

    public function restore(string $id): void
    {
        Page::restore((int) $id);
        flash('success', 'Seite wiederhergestellt.');
        redirect('/admin/pages/trash');
    }

    public function destroy(string $id): void
    {
        Page::destroy((int) $id);
        flash('success', 'Seite endgültig gelöscht.');
        redirect('/admin/pages/trash');
    }

    public function duplicate(string $id): void
    {
        $newId = Page::duplicate((int) $id);
        if ($newId === null) {
            $this->abort();
        }
        flash('success', 'Seite dupliziert (als Entwurf). Du bearbeitest jetzt die Kopie.');
        redirect('/admin/pages/' . $newId . '/edit');
    }

    public function versions(string $id): void
    {
        $page = Page::find((int) $id) ?? $this->abort();
        $this->view('admin/pages/versions', [
            'title' => 'Versionen: ' . $page['title'],
            'active' => 'pages',
            'page' => $page,
            'versions' => \Models\PageVersion::forPage((int) $page['id']),
        ]);
    }

    public function restoreVersion(string $id, string $vid): void
    {
        $page = Page::find((int) $id) ?? $this->abort();
        $version = \Models\PageVersion::find((int) $vid);
        if ($version === null || (int) $version['page_id'] !== (int) $page['id']) {
            flash('error', 'Version nicht gefunden.');
            redirect('/admin/pages/' . $page['id'] . '/versions');
        }
        // Aktuellen Stand sichern, dann alte Version zurückholen.
        \Models\PageVersion::add((int) $page['id'], (string) $page['title'], $page['content'], $_SESSION['username'] ?? null);
        Page::saveContent((int) $page['id'], (string) ($version['content'] ?? '{"rows":[]}'));
        flash('success', 'Version vom ' . format_date_de($version['created_at'], true) . ' wiederhergestellt.');
        redirect('/admin/pages/' . $page['id'] . '/editor');
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
        $layout ??= Layout::default();

        $isGlobal = !empty($page['is_global']);
        $this->view('admin/pages/editor', [
            'title' => 'Inhalt: ' . $page['title'],
            'active' => $isGlobal ? 'globals' : 'pages',
            'editorTitle' => $page['title'],
            'contentJson' => json_encode($content, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE),
            'designHead' => (new \Core\Renderer())->designHead($layout),
            'globalBlocks' => array_map(
                static fn (array $g): array => [(string) $g['id'], $g['title']],
                Page::globals()
            ),
            'saveUrl' => url('/admin/pages/' . $page['id'] . '/content'),
            'backUrl' => url($isGlobal ? '/admin/globals' : '/admin/pages'),
            'backLabel' => $isGlobal ? '← Globale Blöcke' : '← Seiten',
            'previewHref' => $isGlobal ? null : page_url($page),
            'versionsUrl' => url('/admin/pages/' . $page['id'] . '/versions'),
            'mode' => 'page',
            'bodyClass' => 'is-editor',
        ]);
    }

    /** Baum per Drag & Drop neu ordnen (JSON: [{id, parent_id}, …]). */
    public function reorder(): void
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($data) || !is_array($data['items'] ?? null)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Ungültige Daten.']);
            return;
        }
        Page::reorder($data['items']);
        \Core\Cache::clear();
        echo json_encode(['ok' => true]);
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

        // Alten Stand als Version sichern (Versionsverlauf).
        \Models\PageVersion::add((int) $page['id'], (string) $page['title'], $page['content'], $_SESSION['username'] ?? null);

        $clean = $this->sanitizeContent($data);
        Page::saveContent((int) $page['id'], json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"rows":[]}');
        \Core\Cache::clear();
        echo json_encode(['ok' => true]);
    }

    /** Nur bekannte Struktur und Block-Typen übernehmen. */
    private function sanitizeContent(array $data): array
    {
        return BlockRegistry::sanitizeTree($data);
    }

    private function form(?array $page): void
    {
        $this->view('admin/pages/form', [
            'title' => $page ? 'Seite bearbeiten' : 'Neue Seite',
            'active' => 'pages',
            'page' => $page,
            'layouts' => Layout::all(),
            'defaultLayoutId' => Layout::defaultId(),
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
            'meta_title' => trim($_POST['meta_title'] ?? '') ?: null,
            'meta_description' => trim($_POST['meta_description'] ?? '') ?: null,
            'noindex' => isset($_POST['noindex']) ? 1 : 0,
            'lang' => in_array($_POST['lang'] ?? '', cms_langs(), true) ? $_POST['lang'] : cms_default_lang(),
        ];
    }

    private function abort(): never
    {
        flash('error', 'Seite nicht gefunden.');
        redirect('/admin/pages');
    }
}
