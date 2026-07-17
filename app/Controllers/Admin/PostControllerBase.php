<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Models\Post;

/**
 * Gemeinsame CRUD-Logik für News und Events (siehe NewsController /
 * EventsController, die nur Typ und Beschriftungen setzen).
 */
abstract class PostControllerBase extends AdminController
{
    protected string $type;        // 'news' | 'event'
    protected string $basePath;    // '/admin/news' | '/admin/events'
    protected string $labelSingular;
    protected string $labelPlural;

    public function index(): void
    {
        $this->view('admin/posts/index', [
            'title' => $this->labelPlural,
            'active' => str_replace('/admin/', '', $this->basePath),
            'posts' => Post::allByType($this->type),
            'type' => $this->type,
            'basePath' => $this->basePath,
            'labelSingular' => $this->labelSingular,
        ]);
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(string $id): void
    {
        $post = $this->findOrAbort((int) $id);
        $this->form($post);
    }

    public function store(): void
    {
        $data = $this->validated();
        $data['type'] = $this->type;
        Post::create($data);
        flash('success', $this->labelSingular . ' angelegt.');
        redirect($this->basePath);
    }

    public function update(string $id): void
    {
        $post = $this->findOrAbort((int) $id);
        Post::update((int) $post['id'], $this->validated());
        flash('success', $this->labelSingular . ' gespeichert.');
        redirect($this->basePath);
    }

    public function delete(string $id): void
    {
        Post::delete((int) $id);
        flash('success', $this->labelSingular . ' gelöscht.');
        redirect($this->basePath);
    }

    private function form(?array $post): void
    {
        $this->view('admin/posts/form', [
            'title' => $post ? $this->labelSingular . ' bearbeiten' : $this->labelSingular . ' anlegen',
            'active' => str_replace('/admin/', '', $this->basePath),
            'post' => $post,
            'type' => $this->type,
            'basePath' => $this->basePath,
        ]);
    }

    private function validated(): array
    {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect($this->basePath . '/new');
        }

        $toDb = static function (string $key): ?string {
            $value = trim($_POST[$key] ?? '');
            if ($value === '') {
                return null;
            }
            $ts = strtotime($value);
            return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
        };

        return [
            'title' => $title,
            'slug' => slugify(trim($_POST['slug'] ?? '') ?: $title),
            'excerpt' => trim($_POST['excerpt'] ?? '') ?: null,
            'body' => (string) ($_POST['body'] ?? '') ?: null,
            'image' => trim($_POST['image'] ?? '') ?: null,
            'published' => isset($_POST['published']) ? 1 : 0,
            'published_at' => $this->type === 'news' ? ($toDb('published_at') ?? date('Y-m-d H:i:s')) : null,
            'start_at' => $this->type === 'event' ? $toDb('start_at') : null,
            'end_at' => $this->type === 'event' ? $toDb('end_at') : null,
            'location' => $this->type === 'event' ? (trim($_POST['location'] ?? '') ?: null) : null,
        ];
    }

    private function findOrAbort(int $id): array
    {
        $post = Post::find($id);
        if ($post === null || $post['type'] !== $this->type) {
            flash('error', $this->labelSingular . ' nicht gefunden.');
            redirect($this->basePath);
        }
        return $post;
    }
}
