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
