<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Ai;
use Core\AiSchema;
use Core\BlockRegistry;
use Core\Cache;
use Models\Page;
use Models\PageVersion;

/**
 * KI-Assistent: Chat-Oberfläche + Agenten-Schleife. Claude arbeitet über
 * Tools (Seite anlegen/ändern, Bild generieren) direkt im CMS – alles
 * läuft durch dieselbe Validierung wie der normale Editor.
 */
class AiController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    public function index(): void
    {
        $balance = null;
        $balanceError = null;
        if (Ai::configured()) {
            try {
                $balance = Ai::balance()['balance'] ?? null;
            } catch (\Throwable $e) {
                $balanceError = $e->getMessage();
            }
        }
        $this->view('admin/ai/index', [
            'title' => 'KI-Assistent',
            'active' => 'ai',
            'configured' => Ai::configured(),
            'balance' => $balance,
            'balanceError' => $balanceError,
            'buyUrl' => \Models\Setting::get('ai_buy_url', ''),
        ]);
    }

    /** POST /admin/ai/chat – führt einen kompletten Assistenten-Durchlauf aus. */
    public function chat(): void
    {
        header('Content-Type: application/json');
        set_time_limit(300);

        $input = json_decode(file_get_contents('php://input') ?: '', true);
        $history = is_array($input['messages'] ?? null) ? $input['messages'] : [];

        // Verlauf: nur Text-Turns des Clients, auf die letzten 16 begrenzt.
        $messages = [];
        foreach (array_slice($history, -16) as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $text = trim((string) ($turn['text'] ?? ''));
            if ($text !== '' && strlen($text) < 20000) {
                $messages[] = ['role' => $role, 'content' => $text];
            }
        }
        if ($messages === [] || end($messages)['role'] !== 'user') {
            echo json_encode(['ok' => false, 'error' => 'Keine Nachricht übermittelt.']);
            return;
        }

        $actions = [];
        $balance = null;

        try {
            $system = AiSchema::systemPrompt();
            $tools = AiSchema::tools();

            for ($round = 0; $round < 8; $round++) {
                $response = Ai::chat($messages, $tools, $system);
                $balance = $response['balance'] ?? $balance;
                $content = is_array($response['content'] ?? null) ? $response['content'] : [];
                $messages[] = ['role' => 'assistant', 'content' => $content];

                if (($response['stop_reason'] ?? '') !== 'tool_use') {
                    $text = '';
                    foreach ($content as $part) {
                        if (($part['type'] ?? '') === 'text') {
                            $text .= $part['text'];
                        }
                    }
                    echo json_encode([
                        'ok' => true,
                        'text' => trim($text) !== '' ? trim($text) : 'Erledigt.',
                        'actions' => $actions,
                        'balance' => $balance,
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $results = [];
                foreach ($content as $part) {
                    if (($part['type'] ?? '') === 'tool_use') {
                        $results[] = [
                            'type' => 'tool_result',
                            'tool_use_id' => (string) $part['id'],
                            'content' => $this->runTool((string) $part['name'], is_array($part['input'] ?? null) ? $part['input'] : [], $actions, $balance),
                        ];
                    }
                }
                $messages[] = ['role' => 'user', 'content' => $results];
            }

            echo json_encode(['ok' => false, 'error' => 'Zu viele Arbeitsschritte – bitte die Aufgabe kleiner formulieren.', 'actions' => $actions, 'balance' => $balance], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'actions' => $actions, 'balance' => $balance], JSON_UNESCAPED_UNICODE);
        }
    }

    /** Datum/Uhrzeit aus KI-Eingabe in DB-Format normalisieren (oder null). */
    private function dt(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }

    /** Führt ein Tool der KI aus; Rückgabe ist das tool_result (Text). */
    private function runTool(string $name, array $input, array &$actions, mixed &$balance): string
    {
        try {
            switch ($name) {
                case 'create_page':
                    $title = trim((string) ($input['title'] ?? ''));
                    if ($title === '') {
                        return 'FEHLER: Es fehlt ein Seitentitel.';
                    }
                    $content = BlockRegistry::sanitizeTree(is_array($input['content'] ?? null) ? $input['content'] : []);
                    if ($content['rows'] === []) {
                        return 'FEHLER: Das Content-JSON enthielt keine gültigen Zeilen/Blöcke – bitte Format prüfen.';
                    }
                    $parentId = (int) ($input['parent_id'] ?? 0);
                    $id = Page::create([
                        'title' => $title,
                        'slug' => slugify(trim((string) ($input['slug'] ?? '')) ?: $title),
                        'parent_id' => $parentId > 0 && Page::find($parentId) !== null ? $parentId : null,
                        'layout_id' => null,
                        'in_menu' => empty($input['in_menu']) ? 0 : 1,
                        'menu_order' => 0,
                        'published' => 1,
                        'meta_description' => trim((string) ($input['meta_description'] ?? '')) ?: null,
                        'lang' => cms_default_lang(),
                        'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"rows":[]}',
                    ]);
                    Cache::clear();
                    $page = Page::find($id);
                    $actions[] = [
                        'type' => 'page',
                        'label' => 'Seite „' . $title . '“ erstellt',
                        'editorUrl' => url('/admin/pages/' . $id . '/editor'),
                        'viewUrl' => url('/' . ($page['slug'] ?? '')),
                    ];
                    return 'Seite erstellt: id=' . $id . ', slug=' . ($page['slug'] ?? '') . ', URL=' . url('/' . ($page['slug'] ?? ''));

                case 'update_page':
                    $page = Page::find((int) ($input['page_id'] ?? 0));
                    if ($page === null) {
                        return 'FEHLER: Seite nicht gefunden.';
                    }
                    $content = BlockRegistry::sanitizeTree(is_array($input['content'] ?? null) ? $input['content'] : []);
                    if ($content['rows'] === []) {
                        return 'FEHLER: Das Content-JSON enthielt keine gültigen Zeilen/Blöcke.';
                    }
                    PageVersion::add((int) $page['id'], (string) $page['title'], $page['content'], ($_SESSION['username'] ?? '') . ' (KI)');
                    Page::saveContent((int) $page['id'], json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"rows":[]}');
                    Cache::clear();
                    $actions[] = [
                        'type' => 'page',
                        'label' => 'Seite „' . $page['title'] . '“ aktualisiert',
                        'editorUrl' => url('/admin/pages/' . $page['id'] . '/editor'),
                        'viewUrl' => url('/' . $page['slug']),
                    ];
                    return 'Seite id=' . $page['id'] . ' aktualisiert (alter Stand als Version gesichert).';

                case 'get_page':
                    $page = Page::find((int) ($input['page_id'] ?? 0));
                    if ($page === null) {
                        return 'FEHLER: Seite nicht gefunden.';
                    }
                    return 'Titel: ' . $page['title'] . "\nContent-JSON:\n" . ((string) $page['content'] ?: '{"rows":[]}');

                case 'create_post':
                    $type = ($input['type'] ?? '') === 'event' ? 'event' : 'news';
                    $title = trim((string) ($input['title'] ?? ''));
                    if ($title === '') {
                        return 'FEHLER: Titel fehlt.';
                    }
                    $pid = \Models\Post::create([
                        'type' => $type,
                        'title' => $title,
                        'slug' => slugify($title),
                        'excerpt' => trim((string) ($input['excerpt'] ?? '')) ?: null,
                        'body' => (string) ($input['body'] ?? ''),
                        'image' => trim((string) ($input['image'] ?? '')) ?: null,
                        'published' => 1,
                        'published_at' => null,
                        'start_at' => $this->dt($input['start_at'] ?? null),
                        'end_at' => $this->dt($input['end_at'] ?? null),
                        'location' => trim((string) ($input['location'] ?? '')) ?: null,
                    ]);
                    Cache::clear();
                    $post = \Models\Post::find($pid);
                    $actions[] = ['type' => 'page', 'label' => ($type === 'event' ? 'Event' : 'News') . ' „' . $title . '“ erstellt',
                        'editorUrl' => url('/admin/' . ($type === 'event' ? 'events' : 'news') . '/' . $pid . '/edit'),
                        'viewUrl' => url('/' . ($type === 'event' ? 'events' : 'news') . '/' . ($post['slug'] ?? ''))];
                    return ($type === 'event' ? 'Event' : 'News-Beitrag') . ' erstellt: id=' . $pid;

                case 'update_post':
                    $post = \Models\Post::find((int) ($input['post_id'] ?? 0));
                    if ($post === null) {
                        return 'FEHLER: Beitrag nicht gefunden.';
                    }
                    \Models\Post::update((int) $post['id'], [
                        'title' => trim((string) ($input['title'] ?? $post['title'])) ?: $post['title'],
                        'slug' => $post['slug'],
                        'excerpt' => array_key_exists('excerpt', $input) ? (trim((string) $input['excerpt']) ?: null) : $post['excerpt'],
                        'body' => array_key_exists('body', $input) ? (string) $input['body'] : $post['body'],
                        'image' => array_key_exists('image', $input) ? (trim((string) $input['image']) ?: null) : $post['image'],
                        'published' => $post['published'],
                        'published_at' => $post['published_at'],
                        'start_at' => array_key_exists('start_at', $input) ? $this->dt($input['start_at']) : $post['start_at'],
                        'end_at' => array_key_exists('end_at', $input) ? $this->dt($input['end_at']) : $post['end_at'],
                        'location' => array_key_exists('location', $input) ? (trim((string) $input['location']) ?: null) : $post['location'],
                    ]);
                    Cache::clear();
                    $actions[] = ['type' => 'page', 'label' => 'Beitrag „' . $post['title'] . '“ aktualisiert',
                        'editorUrl' => url('/admin/' . ($post['type'] === 'event' ? 'events' : 'news') . '/' . $post['id'] . '/edit'),
                        'viewUrl' => url('/' . ($post['type'] === 'event' ? 'events' : 'news') . '/' . $post['slug'])];
                    return 'Beitrag id=' . $post['id'] . ' aktualisiert.';

                case 'list_posts':
                    $type = ($input['type'] ?? '') === 'event' ? 'event' : 'news';
                    $lines = [];
                    foreach (\Models\Post::allByType($type) as $post) {
                        $date = $type === 'event' ? ($post['start_at'] ?? '') : ($post['published_at'] ?? $post['created_at'] ?? '');
                        $lines[] = 'id=' . $post['id'] . ' „' . $post['title'] . '“' . ($date ? ' (' . $date . ')' : '') . ($post['published'] ? '' : ' [Entwurf]');
                    }
                    return $lines !== [] ? implode("\n", $lines) : 'Noch keine ' . ($type === 'event' ? 'Events' : 'News') . '.';

                case 'list_global_blocks':
                    $lines = [];
                    foreach (Page::globals() as $g) {
                        $lines[] = 'id=' . $g['id'] . ' „' . $g['title'] . '“';
                    }
                    return $lines !== [] ? implode("\n", $lines) : 'Noch keine globalen Blöcke.';

                case 'create_global_block':
                    $title = trim((string) ($input['title'] ?? ''));
                    if ($title === '') {
                        return 'FEHLER: Name fehlt.';
                    }
                    $content = BlockRegistry::sanitizeTree(is_array($input['content'] ?? null) ? $input['content'] : []);
                    if ($content['rows'] === []) {
                        return 'FEHLER: Content-JSON enthielt keine gültigen Blöcke.';
                    }
                    $gid = Page::create([
                        'parent_id' => null, 'title' => $title, 'slug' => 'global-' . slugify($title),
                        'layout_id' => null, 'in_menu' => 0, 'menu_order' => 0, 'published' => 0,
                        'lang' => cms_default_lang(), 'is_global' => 1,
                        'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"rows":[]}',
                    ]);
                    Cache::clear();
                    $actions[] = ['type' => 'page', 'label' => 'Globaler Block „' . $title . '“ erstellt',
                        'editorUrl' => url('/admin/pages/' . $gid . '/editor'), 'viewUrl' => url('/admin/globals')];
                    return 'Globaler Block erstellt: id=' . $gid . ' (im „Globaler Block"-Block über diese id einbettbar).';

                case 'update_global_block':
                    $g = Page::find((int) ($input['block_id'] ?? 0));
                    if ($g === null || (int) ($g['is_global'] ?? 0) !== 1) {
                        return 'FEHLER: Globaler Block nicht gefunden.';
                    }
                    $content = BlockRegistry::sanitizeTree(is_array($input['content'] ?? null) ? $input['content'] : []);
                    if ($content['rows'] === []) {
                        return 'FEHLER: Content-JSON enthielt keine gültigen Blöcke.';
                    }
                    PageVersion::add((int) $g['id'], (string) $g['title'], $g['content'], ($_SESSION['username'] ?? '') . ' (KI)');
                    Page::saveContent((int) $g['id'], json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"rows":[]}');
                    Cache::clear();
                    $actions[] = ['type' => 'page', 'label' => 'Globaler Block „' . $g['title'] . '“ aktualisiert – wirkt überall',
                        'editorUrl' => url('/admin/pages/' . $g['id'] . '/editor'), 'viewUrl' => url('/admin/globals')];
                    return 'Globaler Block id=' . $g['id'] . ' aktualisiert.';

                case 'list_templates':
                    $lines = [];
                    foreach (\Models\Template::all() as $t) {
                        $lines[] = 'id=' . $t['id'] . ' „' . $t['name'] . '“ (Schlüssel: ' . $t['tkey'] . ')';
                    }
                    return $lines !== [] ? implode("\n", $lines) : 'Noch keine Templates.';

                case 'create_template':
                    $name = trim((string) ($input['name'] ?? ''));
                    $key = slugify((string) ($input['key'] ?? ''));
                    if ($name === '' || $key === '') {
                        return 'FEHLER: Name und Schlüssel sind nötig.';
                    }
                    if (\Models\Template::findByKey($key) !== null) {
                        return 'FEHLER: Ein Template mit dem Schlüssel „' . $key . '" existiert bereits.';
                    }
                    $tid = \Models\Template::create($name, $key, (string) ($input['html'] ?? ''));
                    $actions[] = ['type' => 'page', 'label' => 'Template „' . $name . '“ erstellt',
                        'editorUrl' => url('/admin/templates/' . $tid . '/edit'), 'viewUrl' => url('/admin/templates')];
                    return 'Template erstellt: id=' . $tid . ', einbettbar mit {{template:' . $key . '}}.';

                case 'update_template':
                    $t = \Models\Template::find((int) ($input['template_id'] ?? 0));
                    if ($t === null) {
                        return 'FEHLER: Template nicht gefunden.';
                    }
                    if ($t['tkey'] === 'main-menu') {
                        return 'FEHLER: Das Menü-Template wird über den Menü-Designer verwaltet, nicht hier.';
                    }
                    \Models\Template::update((int) $t['id'], trim((string) ($input['name'] ?? $t['name'])) ?: $t['name'], $t['tkey'], (string) ($input['html'] ?? $t['html']));
                    $actions[] = ['type' => 'page', 'label' => 'Template „' . $t['name'] . '“ aktualisiert',
                        'editorUrl' => url('/admin/templates/' . $t['id'] . '/edit'), 'viewUrl' => url('/admin/templates')];
                    return 'Template id=' . $t['id'] . ' aktualisiert.';

                case 'load_font':
                    $fontError = FontController::download((string) ($input['family'] ?? ''));
                    if ($fontError !== null) {
                        return 'FEHLER: ' . $fontError;
                    }
                    Cache::clear();
                    $actions[] = ['type' => 'page', 'label' => 'Schrift „' . trim((string) ($input['family'] ?? '')) . '“ geladen',
                        'editorUrl' => url('/admin/fonts'), 'viewUrl' => url('/admin/layouts')];
                    return 'Schrift „' . trim((string) ($input['family'] ?? '')) . '“ heruntergeladen und lokal gespeichert. Weise den Nutzer darauf hin, sie im gewünschten Layout unter Design als Überschriften-/Textschrift auszuwählen.';

                case 'list_media':
                    $folderId = null;
                    $folderName = trim((string) ($input['folder'] ?? ''));
                    if ($folderName !== '') {
                        $folder = \Models\MediaFolder::findByName($folderName);
                        if ($folder === null) {
                            $names = array_map(static fn (array $f): string => $f['name'], \Models\MediaFolder::all());
                            return 'FEHLER: Ordner „' . $folderName . '“ nicht gefunden. Vorhandene Ordner: ' . ($names !== [] ? implode(', ', $names) : 'keine');
                        }
                        $folderId = (int) $folder['id'];
                    }
                    $found = \Models\Media::search(trim((string) ($input['search'] ?? '')), $folderId, 40);
                    $lines = [];
                    foreach ($found as $item) {
                        if (str_starts_with((string) $item['mime'], 'image/')) {
                            $lines[] = url('/' . $item['path']) . ' — „' . $item['filename'] . '“'
                                . (!empty($item['alt']) ? ' (Alt: ' . $item['alt'] . ')' : '')
                                . ($item['width'] ? ' ' . $item['width'] . '×' . $item['height'] . 'px' : '');
                        }
                    }
                    return $lines !== []
                        ? "Gefundene Bilder:\n" . implode("\n", $lines)
                        : 'Keine passenden Bilder gefunden – nutze generate_image oder frage den Nutzer.';

                case 'get_layout':
                    $layout = \Models\Layout::find((int) ($input['layout_id'] ?? 0));
                    if ($layout === null) {
                        return 'FEHLER: Layout nicht gefunden.';
                    }
                    $builder = trim((string) ($layout['builder'] ?? ''));
                    if ($builder !== '') {
                        return 'Layout „' . $layout['name'] . '“ (id=' . $layout['id'] . ', visuell). Builder-JSON:' . "\n" . $builder;
                    }
                    return 'Layout „' . $layout['name'] . '“ (id=' . $layout['id'] . ', klassisch/HTML – NICHT per update_layout änderbar). HTML:' . "\n" . (string) $layout['html'];

                case 'update_layout':
                    $layout = \Models\Layout::find((int) ($input['layout_id'] ?? 0));
                    if ($layout === null) {
                        return 'FEHLER: Layout nicht gefunden.';
                    }
                    if (trim((string) ($layout['builder'] ?? '')) === '') {
                        return 'FEHLER: Dieses Layout ist klassisch (HTML) und kann nicht per update_layout geändert werden.';
                    }
                    $builder = BlockRegistry::sanitizeTree(is_array($input['builder'] ?? null) ? $input['builder'] : []);
                    if ($builder['rows'] === []) {
                        return 'FEHLER: Das Builder-JSON enthielt keine gültigen Zeilen/Blöcke.';
                    }
                    $contentBlocks = 0;
                    foreach ($builder['rows'] as $row) {
                        foreach ($row['columns'] ?? [] as $column) {
                            foreach ($column['blocks'] ?? [] as $block) {
                                if (($block['type'] ?? '') === 'l-content') {
                                    $contentBlocks++;
                                }
                            }
                        }
                    }
                    if ($contentBlocks !== 1) {
                        return 'FEHLER: Das Layout muss genau EINEN l-content-Block enthalten (gefunden: ' . $contentBlocks . '). Bitte das vorhandene Layout als Basis nehmen.';
                    }
                    \Models\Layout::saveBuilder((int) $layout['id'], json_encode($builder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null);
                    Cache::clear();
                    $actions[] = [
                        'type' => 'page',
                        'label' => 'Layout „' . $layout['name'] . '“ aktualisiert – gilt auf allen Seiten',
                        'editorUrl' => url('/admin/layouts/' . $layout['id'] . '/builder'),
                        'viewUrl' => url('/'),
                    ];
                    return 'Layout id=' . $layout['id'] . ' aktualisiert – die Änderung ist auf allen Seiten mit diesem Layout aktiv.';

                case 'generate_image':
                    $prompt = trim((string) ($input['prompt'] ?? ''));
                    if ($prompt === '') {
                        return 'FEHLER: Bild-Prompt fehlt.';
                    }
                    $result = Ai::image($prompt);
                    $balance = $result['balance'] ?? $balance;
                    $bytes = base64_decode((string) ($result['image_b64'] ?? ''), true);
                    if ($bytes === false || $bytes === '') {
                        return 'FEHLER: Der Bild-Dienst hat kein gültiges Bild geliefert.';
                    }
                    $stored = MediaController::storeBytes($bytes, (string) ($input['filename'] ?? 'ki-bild') . '.png', 'image/png');
                    if ($stored === null) {
                        return 'FEHLER: Das Bild konnte nicht gespeichert werden.';
                    }
                    $actions[] = [
                        'type' => 'image',
                        'label' => 'Bild generiert',
                        'url' => $stored['url'],
                        'thumb' => $stored['thumb'],
                    ];
                    return 'Bild gespeichert. URL: ' . $stored['url'];

                default:
                    return 'FEHLER: Unbekanntes Tool „' . $name . '“.';
            }
        } catch (\Throwable $e) {
            return 'FEHLER: ' . $e->getMessage();
        }
    }
}
