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
            'history' => \Models\AiMessage::recent((int) ($_SESSION['user_id'] ?? 0)),
        ]);
    }

    /** POST /admin/ai/plan – zerlegt die Anfrage in Schritte (führt nichts aus). */
    public function plan(): void
    {
        header('Content-Type: application/json');
        set_time_limit(120);

        $input = json_decode(file_get_contents('php://input') ?: '', true);
        $history = is_array($input['messages'] ?? null) ? $input['messages'] : [];
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
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $newUserText = (string) (end($messages)['content'] ?? '');

        try {
            $response = Ai::chat($messages, AiSchema::planTool(), AiSchema::planPrompt(), true);
            $balance = $response['balance'] ?? null;
            $steps = [];
            $intro = '';
            foreach (is_array($response['content'] ?? null) ? $response['content'] : [] as $part) {
                if (($part['type'] ?? '') === 'text') {
                    $intro .= $part['text'];
                }
                if (($part['type'] ?? '') === 'tool_use' && ($part['name'] ?? '') === 'propose_plan') {
                    foreach ((array) ($part['input']['steps'] ?? []) as $s) {
                        $title = trim((string) ($s['title'] ?? ''));
                        if ($title !== '') {
                            $steps[] = ['title' => $title, 'detail' => trim((string) ($s['detail'] ?? '')), 'fast' => !empty($s['fast'])];
                        }
                    }
                }
            }
            $steps = array_slice($steps, 0, 12);

            if ($userId > 0 && $steps !== []) {
                $lines = [];
                foreach ($steps as $i => $s) {
                    $lines[] = ($i + 1) . '. ' . $s['title'] . ($s['detail'] !== '' ? ' – ' . $s['detail'] : '');
                }
                $this->saveTurn($userId, $newUserText, "Plan:\n" . implode("\n", $lines));
            }

            echo json_encode(['ok' => true, 'steps' => $steps, 'intro' => trim($intro), 'balance' => $balance], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** POST /admin/ai/help – kontextbezogene Hilfe zur aktuellen Backend-Seite. */
    public function help(): void
    {
        header('Content-Type: application/json');
        set_time_limit(60);

        $input = json_decode(file_get_contents('php://input') ?: '', true);
        $page = trim((string) ($input['page'] ?? ''));
        $pageTitle = trim((string) ($input['title'] ?? ''));
        $question = trim((string) ($input['question'] ?? ''));
        $history = is_array($input['messages'] ?? null) ? $input['messages'] : [];

        $messages = [];
        foreach (array_slice($history, -10) as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $text = trim((string) ($turn['text'] ?? ''));
            if ($text !== '' && strlen($text) < 8000) {
                $messages[] = ['role' => $role, 'content' => $text];
            }
        }
        if ($question === '') {
            $question = 'Was kann ich auf dieser Seite tun? Erkläre es mir kurz und praktisch.';
        }
        $messages[] = ['role' => 'user', 'content' => $question];

        try {
            $response = Ai::chat($messages, [], AiSchema::helpPrompt($page, $pageTitle), true);
            $text = '';
            foreach (is_array($response['content'] ?? null) ? $response['content'] : [] as $part) {
                if (($part['type'] ?? '') === 'text') {
                    $text .= $part['text'];
                }
            }
            echo json_encode(['ok' => true, 'text' => trim($text) !== '' ? trim($text) : '—', 'balance' => $response['balance'] ?? null], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** POST /admin/ai/clear – gespeicherten Gesprächsverlauf löschen. */
    public function clear(): void
    {
        \Models\AiMessage::clear((int) ($_SESSION['user_id'] ?? 0));
        flash('success', 'Gesprächsverlauf gelöscht.');
        redirect('/admin/ai');
    }

    /** Speichert einen Frage-/Antwort-Turn im Verlauf des Nutzers. */
    private function saveTurn(int $userId, string $userText, string $assistantText): void
    {
        if ($userId <= 0 || trim($userText) === '') {
            return;
        }
        \Models\AiMessage::add($userId, 'user', $userText);
        if (trim($assistantText) !== '') {
            \Models\AiMessage::add($userId, 'assistant', $assistantText);
        }
    }

    /**
     * Wandelt die KI-Eingaben für Staffelpreise/Varianten/Verknüpfungen in die
     * gespeicherte JSON-Form (Preise in Cent). Nicht übergebene Felder behalten
     * den bestehenden Wert ($existing), damit ein Update sie nicht löscht.
     *
     * @return array{tier_prices:?string,options:?string,cross_sell:?string,accessories:?string}
     */
    private function shopExtrasJson(array $input, array $existing): array
    {
        $out = [
            'tier_prices' => $existing['tier_prices'] ?? null,
            'options' => $existing['options'] ?? null,
            'cross_sell' => $existing['cross_sell'] ?? null,
            'accessories' => $existing['accessories'] ?? null,
        ];

        if (array_key_exists('tier_prices', $input) && is_array($input['tier_prices'])) {
            $tiers = [];
            foreach ($input['tier_prices'] as $t) {
                $min = (int) ($t['min'] ?? 0);
                $price = isset($t['price']) ? \Core\Shop::parsePrice((string) $t['price']) : 0;
                if ($min > 1 && $price > 0) {
                    $tiers[] = ['min' => $min, 'price' => $price];
                }
            }
            $out['tier_prices'] = $tiers !== [] ? json_encode($tiers) : null;
        }

        if (array_key_exists('variants', $input) && is_array($input['variants'])) {
            $groups = [];
            foreach ($input['variants'] as $g) {
                $gname = trim((string) ($g['name'] ?? ''));
                $choices = [];
                foreach ((array) ($g['choices'] ?? []) as $c) {
                    $label = trim((string) ($c['label'] ?? ''));
                    if ($label !== '') {
                        $choices[] = [
                            'label' => $label,
                            'diff' => isset($c['surcharge']) ? \Core\Shop::parsePrice((string) $c['surcharge']) : 0,
                        ];
                    }
                }
                if ($gname !== '' && $choices !== []) {
                    $groups[] = ['name' => $gname, 'choices' => $choices];
                }
            }
            $out['options'] = $groups !== [] ? json_encode($groups) : null;
        }

        foreach (['cross_sell', 'accessories'] as $rel) {
            if (array_key_exists($rel, $input) && is_array($input[$rel])) {
                $ids = array_values(array_filter(array_map('intval', $input[$rel]), static fn ($i) => $i > 0));
                $out[$rel] = $ids !== [] ? json_encode($ids) : null;
            }
        }

        return $out;
    }

    /** Baut aus der KI-Eingabe die Daten für eine Versandart (kg→g, €→Cent). */
    private function shippingDataFromInput(array $input, array $existing): array
    {
        $countries = $existing['countries'] ?? null;
        if (array_key_exists('countries', $input) && is_array($input['countries'])) {
            $list = array_values(array_filter(array_map(static fn ($c) => trim((string) $c), $input['countries']), static fn ($c) => $c !== ''));
            $countries = $list !== [] ? json_encode($list, JSON_UNESCAPED_UNICODE) : null;
        }

        $weightTiers = $existing['weight_tiers'] ?? null;
        if (array_key_exists('weight_tiers', $input) && is_array($input['weight_tiers'])) {
            $tiers = [];
            foreach ($input['weight_tiers'] as $t) {
                $grams = (int) round(((float) ($t['up_to_kg'] ?? 0)) * 1000);
                if ($grams > 0) {
                    $tiers[] = ['max' => $grams, 'price' => \Core\Shop::parsePrice((string) ($t['price'] ?? '0'))];
                }
            }
            usort($tiers, static fn ($a, $b) => $a['max'] <=> $b['max']);
            $weightTiers = $tiers !== [] ? json_encode($tiers) : null;
        }

        $freeFrom = $existing['free_from'] ?? null;
        if (array_key_exists('free_from', $input)) {
            $freeFrom = ($input['free_from'] !== '' && $input['free_from'] !== null) ? \Core\Shop::parsePrice((string) $input['free_from']) : null;
        }

        return [
            'name' => trim((string) ($input['name'] ?? '')) ?: (string) ($existing['name'] ?? ''),
            'description' => array_key_exists('description', $input) ? (trim((string) $input['description']) ?: null) : ($existing['description'] ?? null),
            'price' => (isset($input['price']) && $input['price'] !== '') ? \Core\Shop::parsePrice((string) $input['price']) : (int) ($existing['price'] ?? 0),
            'free_from' => $freeFrom,
            'countries' => $countries,
            'weight_tiers' => $weightTiers,
            'active' => array_key_exists('active', $input) ? ((int) $input['active'] ? 1 : 0) : (int) ($existing['active'] ?? 1),
            'position' => (int) ($existing['position'] ?? 0),
        ];
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

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $newUserText = (string) (end($messages)['content'] ?? '');
        // Plan-Modus markiert einfache Schritte: dann das schnelle Modell nutzen.
        $fast = !empty($input['fast']);

        $actions = [];
        $balance = null;

        try {
            $system = AiSchema::systemPrompt();
            $tools = AiSchema::tools();

            for ($round = 0; $round < 16; $round++) {
                $response = Ai::chat($messages, $tools, $system, $fast);
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
                    $reply = trim($text) !== '' ? trim($text) : 'Erledigt.';
                    $this->saveTurn($userId, $newUserText, $reply);
                    echo json_encode([
                        'ok' => true,
                        'text' => $reply,
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

            $reply = 'Ich habe die Aufgabe in mehreren Schritten bearbeitet (siehe die Aktionen oben) und bin dabei an die Schrittgrenze gestoßen. Falls noch etwas fehlt, fasse den restlichen Wunsch bitte kurz in einer Folgeanweisung zusammen.';
            $this->saveTurn($userId, $newUserText, $reply);
            echo json_encode([
                'ok' => true,
                'text' => $reply,
                'actions' => $actions,
                'balance' => $balance,
            ], JSON_UNESCAPED_UNICODE);
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
                    return 'Schrift „' . trim((string) ($input['family'] ?? '')) . '“ heruntergeladen und lokal gespeichert. Du kannst sie jetzt mit set_layout_design dem Layout als Überschriften-/Text-/Ebenen-Schrift zuweisen.';

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

                case 'set_layout_design':
                    $layout = \Models\Layout::find((int) ($input['layout_id'] ?? 0));
                    if ($layout === null) {
                        return 'FEHLER: Layout nicht gefunden.';
                    }
                    $design = json_decode((string) ($layout['design'] ?? ''), true);
                    if (!is_array($design)) {
                        $design = [];
                    }
                    $design['colors'] = is_array($design['colors'] ?? null) ? $design['colors'] : [];
                    $design['fonts'] = is_array($design['fonts'] ?? null) ? $design['fonts'] : [];

                    $changed = [];
                    $fontsInput = is_array($input['fonts'] ?? null) ? $input['fonts'] : [];
                    foreach (['heading', 'body', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $slot) {
                        $family = trim((string) ($fontsInput[$slot] ?? ''));
                        if ($family === '') {
                            continue;
                        }
                        $font = \Models\Font::findByFolder(slugify($family));
                        if ($font === null) {
                            // Schrift noch nicht vorhanden → automatisch von Google Fonts laden.
                            $err = FontController::download($family);
                            if ($err !== null && !str_contains($err, 'bereits installiert')) {
                                return 'FEHLER beim Laden der Schrift „' . $family . '“: ' . $err;
                            }
                            $font = \Models\Font::findByFolder(slugify($family));
                        }
                        if ($font === null) {
                            return 'FEHLER: Schrift „' . $family . '“ konnte nicht zugewiesen werden.';
                        }
                        $design['fonts'][$slot] = (int) $font['id'];
                        $changed[] = $slot . '=' . $family;
                    }

                    $colorsInput = is_array($input['colors'] ?? null) ? $input['colors'] : [];
                    foreach (['primary', 'accent', 'text', 'bg', 'surface'] as $key) {
                        $value = strtolower(trim((string) ($colorsInput[$key] ?? '')));
                        if (preg_match('/^#[0-9a-f]{6}$/', $value)) {
                            $design['colors'][$key] = $value;
                            $changed[] = $key . '=' . $value;
                        }
                    }

                    if ($changed === []) {
                        return 'FEHLER: Keine gültigen Schriften/Farben angegeben (Schrift-Slots: heading, body, h1–h6; Farben als #rrggbb).';
                    }

                    \Models\Layout::saveDesign((int) $layout['id'], json_encode($design, JSON_UNESCAPED_UNICODE) ?: null);
                    Cache::clear();
                    $actions[] = [
                        'type' => 'page',
                        'label' => 'Design von Layout „' . $layout['name'] . '“ aktualisiert',
                        'editorUrl' => url('/admin/layouts/' . $layout['id'] . '/edit'),
                        'viewUrl' => url('/'),
                    ];
                    return 'Layout-Design (id=' . $layout['id'] . ') gesetzt: ' . implode(', ', $changed) . '. Wirkt sofort auf allen Seiten mit diesem Layout.';

                case 'set_logo':
                    $imageUrl = trim((string) ($input['image_url'] ?? ''));
                    if ($imageUrl === '') {
                        return 'FEHLER: image_url fehlt (Logo-Bild-URL).';
                    }
                    $lid = (int) ($input['layout_id'] ?? 0);
                    $layout = $lid > 0 ? \Models\Layout::find($lid) : \Models\Layout::default();
                    if ($layout === null) {
                        return 'FEHLER: Layout nicht gefunden.';
                    }
                    $builder = json_decode((string) ($layout['builder'] ?? ''), true);
                    if (!is_array($builder) || !is_array($builder['rows'] ?? null)) {
                        return 'FEHLER: Dieses Layout ist klassisch (HTML) – das Logo bitte direkt im Layout-HTML setzen.';
                    }
                    $found = false;
                    foreach ($builder['rows'] as $ri => $row) {
                        foreach ($row['columns'] ?? [] as $ci => $column) {
                            foreach ($column['blocks'] ?? [] as $bi => $block) {
                                if (($block['type'] ?? '') === 'l-brand') {
                                    $data = is_array($block['data'] ?? null) ? $block['data'] : [];
                                    $data['logo'] = $imageUrl;
                                    if (array_key_exists('show_name', $input)) {
                                        $data['show_name'] = !empty($input['show_name']) ? 1 : 0;
                                    }
                                    $builder['rows'][$ri]['columns'][$ci]['blocks'][$bi]['data'] = $data;
                                    $found = true;
                                }
                            }
                        }
                    }
                    if (!$found) {
                        return 'FEHLER: Dieses Layout hat keinen Logo-/Marken-Block (l-brand).';
                    }
                    $builder = BlockRegistry::sanitizeTree($builder);
                    \Models\Layout::saveBuilder((int) $layout['id'], json_encode($builder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null);
                    Cache::clear();
                    $actions[] = [
                        'type' => 'page',
                        'label' => 'Logo im Layout „' . $layout['name'] . '“ gesetzt',
                        'editorUrl' => url('/admin/layouts/' . $layout['id'] . '/builder'),
                        'viewUrl' => url('/'),
                    ];
                    return 'Logo gesetzt (Layout id=' . $layout['id'] . '). Erscheint sofort im Kopf aller Seiten mit diesem Layout.';

                case 'list_shop_categories':
                    $lines = [];
                    foreach (\Models\ShopCategory::tree() as $c) {
                        $lines[] = 'id=' . $c['id'] . ' ' . str_repeat('— ', (int) $c['depth']) . '„' . $c['name'] . '“'
                            . ($c['parent_id'] ? ' (Unterkategorie von id=' . $c['parent_id'] . ')' : '');
                    }
                    return $lines !== [] ? implode("\n", $lines) : 'Noch keine Shop-Kategorien.';

                case 'create_shop_category':
                    $name = trim((string) ($input['name'] ?? ''));
                    if ($name === '') {
                        return 'FEHLER: Kategoriename fehlt.';
                    }
                    $pid = (int) ($input['parent_id'] ?? 0);
                    $cid = \Models\ShopCategory::create([
                        'name' => $name,
                        'slug' => '',
                        'parent_id' => $pid > 0 && \Models\ShopCategory::find($pid) !== null ? $pid : 0,
                        'description' => trim((string) ($input['description'] ?? '')) ?: null,
                        'image' => trim((string) ($input['image'] ?? '')) ?: null,
                        'position' => 0,
                    ]);
                    Cache::clear();
                    $cat = \Models\ShopCategory::find($cid);
                    $actions[] = [
                        'type' => 'link',
                        'label' => 'Kategorie „' . $name . '“ angelegt',
                        'editorUrl' => url('/admin/shop/categories/' . $cid . '/edit'),
                        'viewUrl' => \Core\Shop::enabled() ? \Core\Shop::url('kategorie/' . ($cat['slug'] ?? '')) : null,
                    ];
                    return 'Kategorie angelegt: id=' . $cid . ', Name=' . $name;

                case 'list_shop_products':
                    $search = trim((string) ($input['search'] ?? ''));
                    $cats = [];
                    foreach (\Models\ShopCategory::all() as $c) {
                        $cats[(int) $c['id']] = $c['name'];
                    }
                    $lines = [];
                    foreach (\Models\ShopProduct::all() as $pr) {
                        if ($search !== '' && stripos($pr['name'], $search) === false) {
                            continue;
                        }
                        $lines[] = 'id=' . $pr['id'] . ' „' . $pr['name'] . '“ '
                            . \Core\Shop::formatPrice((int) $pr['price'])
                            . ' [' . ($cats[(int) ($pr['category_id'] ?? 0)] ?? 'ohne Kategorie') . ']'
                            . ((int) $pr['active'] ? '' : ' (inaktiv)');
                    }
                    return $lines !== [] ? implode("\n", $lines) : 'Noch keine Produkte.';

                case 'create_shop_product':
                    $name = trim((string) ($input['name'] ?? ''));
                    if ($name === '') {
                        return 'FEHLER: Produktname fehlt.';
                    }
                    if (!isset($input['price'])) {
                        return 'FEHLER: Preis fehlt (in Euro, z. B. 19.90).';
                    }
                    $catId = (int) ($input['category_id'] ?? 0);
                    $extras = $this->shopExtrasJson($input, []);
                    $pidNew = \Models\ShopProduct::create([
                        'name' => $name,
                        'slug' => '',
                        'sku' => trim((string) ($input['sku'] ?? '')) ?: null,
                        'category_id' => $catId > 0 && \Models\ShopCategory::find($catId) !== null ? $catId : 0,
                        'price' => \Core\Shop::parsePrice((string) $input['price']),
                        'compare_price' => isset($input['compare_price']) ? \Core\Shop::parsePrice((string) $input['compare_price']) : null,
                        'short_desc' => trim((string) ($input['short_desc'] ?? '')) ?: null,
                        'description' => trim((string) ($input['description'] ?? '')) ?: null,
                        'image' => trim((string) ($input['image'] ?? '')) ?: null,
                        'gallery' => null,
                        'tier_prices' => $extras['tier_prices'],
                        'options' => $extras['options'],
                        'cross_sell' => $extras['cross_sell'],
                        'accessories' => $extras['accessories'],
                        'stock' => isset($input['stock']) && $input['stock'] !== '' ? (int) $input['stock'] : null,
                        'weight' => isset($input['weight']) && $input['weight'] !== '' ? (int) round(((float) $input['weight']) * 1000) : null,
                        'active' => 1,
                        'featured' => (int) ($input['featured'] ?? 0) ? 1 : 0,
                        'position' => 0,
                    ]);
                    Cache::clear();
                    $prod = \Models\ShopProduct::find($pidNew);
                    $hint = \Core\Shop::enabled() ? '' : ' Hinweis: Der Shop ist noch nicht aktiviert – unter Shop-Einstellungen aktivieren, damit das Produkt auf der Website erscheint.';
                    $actions[] = [
                        'type' => 'link',
                        'label' => 'Produkt „' . $name . '“ angelegt',
                        'editorUrl' => url('/admin/shop/products/' . $pidNew . '/edit'),
                        'viewUrl' => \Core\Shop::enabled() ? \Core\Shop::url('produkt/' . ($prod['slug'] ?? '')) : null,
                    ];
                    return 'Produkt angelegt: id=' . $pidNew . ', Name=' . $name . ', Preis=' . \Core\Shop::formatPrice((int) $prod['price']) . '.' . $hint;

                case 'update_shop_product':
                    $prod = \Models\ShopProduct::find((int) ($input['product_id'] ?? 0));
                    if ($prod === null) {
                        return 'FEHLER: Produkt nicht gefunden.';
                    }
                    $data = [
                        'name' => trim((string) ($input['name'] ?? '')) ?: $prod['name'],
                        'slug' => $prod['slug'],
                        'sku' => $prod['sku'],
                        'category_id' => array_key_exists('category_id', $input) ? (int) $input['category_id'] : (int) ($prod['category_id'] ?? 0),
                        'price' => isset($input['price']) ? \Core\Shop::parsePrice((string) $input['price']) : (int) $prod['price'],
                        'compare_price' => isset($input['compare_price']) ? \Core\Shop::parsePrice((string) $input['compare_price']) : ($prod['compare_price'] ?? null),
                        'short_desc' => array_key_exists('short_desc', $input) ? (trim((string) $input['short_desc']) ?: null) : ($prod['short_desc'] ?? null),
                        'description' => array_key_exists('description', $input) ? (trim((string) $input['description']) ?: null) : ($prod['description'] ?? null),
                        'image' => array_key_exists('image', $input) ? (trim((string) $input['image']) ?: null) : ($prod['image'] ?? null),
                        'gallery' => $prod['gallery'] ?? null,
                        // Staffelpreise/Varianten/Verknüpfungen: neue Werte übernehmen,
                        // sonst die bestehenden erhalten (nicht überschreiben).
                        ...$this->shopExtrasJson($input, $prod),
                        'stock' => array_key_exists('stock', $input) && $input['stock'] !== '' ? (int) $input['stock'] : ($prod['stock'] ?? null),
                        'weight' => array_key_exists('weight', $input) && $input['weight'] !== '' ? (int) round(((float) $input['weight']) * 1000) : ($prod['weight'] ?? null),
                        'active' => array_key_exists('active', $input) ? ((int) $input['active'] ? 1 : 0) : (int) $prod['active'],
                        'featured' => array_key_exists('featured', $input) ? ((int) $input['featured'] ? 1 : 0) : (int) $prod['featured'],
                        'position' => (int) $prod['position'],
                    ];
                    \Models\ShopProduct::update((int) $prod['id'], $data);
                    Cache::clear();
                    $actions[] = [
                        'type' => 'link',
                        'label' => 'Produkt „' . $data['name'] . '“ aktualisiert',
                        'editorUrl' => url('/admin/shop/products/' . $prod['id'] . '/edit'),
                        'viewUrl' => \Core\Shop::enabled() ? \Core\Shop::url('produkt/' . $prod['slug']) : null,
                    ];
                    return 'Produkt id=' . $prod['id'] . ' aktualisiert.';

                case 'list_shipping':
                    $lines = [];
                    foreach (\Models\ShopShipping::all() as $m) {
                        $countries = \Models\ShopShipping::countries($m);
                        $tiers = [];
                        foreach (\Models\ShopShipping::weightTiers($m) as $t) {
                            $tiers[] = 'bis ' . rtrim(rtrim(number_format($t['max'] / 1000, 3, '.', ''), '0'), '.') . ' kg → ' . \Core\Shop::formatPrice($t['price']);
                        }
                        $lines[] = 'id=' . $m['id'] . ' „' . $m['name'] . '“'
                            . ($m['active'] ? '' : ' (deaktiviert)')
                            . ' | Länder: ' . ($countries === [] ? 'alle' : implode(', ', $countries))
                            . ' | ' . ($tiers === [] ? 'Pauschal ' . \Core\Shop::formatPrice((int) $m['price']) : 'Staffeln: ' . implode('; ', $tiers))
                            . (($m['free_from'] ?? null) !== null ? ' | gratis ab ' . \Core\Shop::formatPrice((int) $m['free_from']) : '');
                    }
                    return $lines !== [] ? implode("\n", $lines) : 'Noch keine Versandarten.';

                case 'create_shipping':
                    $name = trim((string) ($input['name'] ?? ''));
                    if ($name === '') {
                        return 'FEHLER: Name der Versandart fehlt.';
                    }
                    $sidNew = \Models\ShopShipping::create($this->shippingDataFromInput($input, []));
                    Cache::clear();
                    $actions[] = ['type' => 'link', 'label' => 'Versandart „' . $name . '“ angelegt', 'editorUrl' => url('/admin/shop/settings'), 'viewUrl' => null];
                    return 'Versandart angelegt: id=' . $sidNew . ', „' . $name . '“.'
                        . (\Core\Shop::enabled() ? '' : ' Hinweis: Der Shop ist noch nicht aktiviert.');

                case 'update_shipping':
                    $ship = \Models\ShopShipping::find((int) ($input['shipping_id'] ?? 0));
                    if ($ship === null) {
                        return 'FEHLER: Versandart nicht gefunden.';
                    }
                    \Models\ShopShipping::update((int) $ship['id'], $this->shippingDataFromInput($input, $ship));
                    Cache::clear();
                    $actions[] = ['type' => 'link', 'label' => 'Versandart aktualisiert', 'editorUrl' => url('/admin/shop/settings'), 'viewUrl' => null];
                    return 'Versandart id=' . $ship['id'] . ' aktualisiert.';

                case 'create_design':
                    $name = trim((string) ($input['name'] ?? ''));
                    if ($name === '') {
                        return 'FEHLER: Design-Name fehlt.';
                    }
                    $hex = static fn ($v, $fallback) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v) ? strtolower((string) $v) : $fallback;
                    $ci = is_array($input['colors'] ?? null) ? $input['colors'] : [];
                    $colors = [
                        'primary' => $hex($ci['primary'] ?? '', '#4f46e5'),
                        'accent' => $hex($ci['accent'] ?? '', '#f59e0b'),
                        'text' => $hex($ci['text'] ?? '', '#1e293b'),
                        'bg' => $hex($ci['bg'] ?? '', '#ffffff'),
                        'surface' => $hex($ci['surface'] ?? '', '#f1f5f9'),
                    ];
                    $hi = is_array($input['header'] ?? null) ? $input['header'] : [];
                    // Kopf-Hintergrund: Hex oder sicherer CSS-Verlauf (keine CSS-Ausbrüche).
                    $rawHeaderBg = (string) ($hi['bg'] ?? '');
                    $headerBg = preg_match('/^#[0-9a-fA-F]{6}$/', $rawHeaderBg)
                        ? strtolower($rawHeaderBg)
                        : (preg_match('/^(linear|radial)-gradient\([#0-9a-zA-Z(),.%\s-]+\)$/', $rawHeaderBg) ? $rawHeaderBg : $colors['bg']);
                    $headerText = $hex($hi['text'] ?? '', '#111111');

                    $si = is_array($input['style'] ?? null) ? $input['style'] : [];
                    $enum = static fn ($v, array $allowed, $fallback) => in_array($v, $allowed, true) ? $v : $fallback;
                    $tokens = [
                        'header' => $enum($si['header_layout'] ?? '', ['bar', 'center'], 'bar'),
                        'radius' => max(0, min(40, (int) ($si['radius'] ?? 12))),
                        'button' => $enum($si['button'] ?? '', ['round', 'pill', 'sharp'], 'round'),
                        'hero' => max(30, min(100, (int) ($si['hero'] ?? 65))),
                        'container' => max(800, min(1400, (int) ($si['container'] ?? 1100))),
                        'section' => max(0, min(140, (int) ($si['section'] ?? 0))),
                        'shadow' => $enum($si['shadow'] ?? '', ['none', 'soft', 'strong'], 'soft'),
                        'scale' => max(14, min(22, (float) ($si['scale'] ?? 16))),
                        'headingWeight' => max(400, min(900, (int) ($si['heading_weight'] ?? 800))),
                        'headingSpacing' => (string) ($si['heading_spacing'] ?? '-.3px'),
                        'uppercase' => (bool) ($si['uppercase'] ?? false),
                        'headingFont' => $enum($si['heading_font'] ?? '', ['sans', 'serif', 'mono'], 'sans'),
                        'pack' => $enum($si['component_style'] ?? '', ['panel', 'soft', 'bold', 'editorial', 'slant'], 'panel'),
                    ];
                    $key = \Core\Themes::saveCustom($name, trim((string) ($input['description'] ?? '')), $colors, $headerBg, $headerText, $tokens);
                    \Core\Themes::apply($key);
                    Cache::clear();
                    $actions[] = [
                        'type' => 'link',
                        'label' => 'Design „' . $name . '“ erstellt & aktiviert',
                        'editorUrl' => url('/admin/themes'),
                        'viewUrl' => url('/'),
                    ];
                    return 'Design „' . $name . '" erstellt, unter Designs gespeichert und aktiviert. Die ganze Website hat jetzt die neue Optik.';

                case 'fetch_url':
                    $url = trim((string) ($input['url'] ?? ''));
                    if ($url === '') {
                        return 'FEHLER: Es fehlt eine URL.';
                    }
                    $page = \Core\WebFetch::fetchPage($url);
                    if (!($page['ok'] ?? false)) {
                        return 'FEHLER: ' . ($page['error'] ?? 'Seite nicht abrufbar.');
                    }
                    $out = "HINWEIS: Fremde Inhalte/Bilder können urheberrechtlich geschützt sein – nur als Vorlage nutzen, Texte selbst umformulieren.\n"
                        . 'Titel: ' . $page['title'] . "\n"
                        . 'Beschreibung: ' . $page['description'] . "\n"
                        . "Überschriften:\n" . implode("\n", $page['headings'] ?? []) . "\n\n"
                        . "Textauszug:\n" . $page['text'] . "\n\n"
                        . "Gefundene Bilder (für download_image):\n" . implode("\n", $page['images'] ?? []);
                    return mb_substr($out, 0, 12000);

                case 'download_image':
                    $url = trim((string) ($input['url'] ?? ''));
                    if ($url === '') {
                        return 'FEHLER: Es fehlt eine Bild-URL.';
                    }
                    $img = \Core\WebFetch::fetchImage($url);
                    if (!($img['ok'] ?? false)) {
                        return 'FEHLER: ' . ($img['error'] ?? 'Bild nicht abrufbar.');
                    }
                    $fname = trim((string) ($input['filename'] ?? '')) ?: 'bild-' . substr(md5($url), 0, 8);
                    $fname = (slugify($fname) ?: 'bild') . '.' . $img['ext'];
                    $stored = MediaController::storeBytes($img['bytes'], $fname, $img['mime']);
                    if ($stored === null) {
                        return 'FEHLER: Das Bild konnte nicht gespeichert werden.';
                    }
                    $actions[] = [
                        'type' => 'image',
                        'label' => 'Bild heruntergeladen',
                        'url' => $stored['url'],
                        'thumb' => $stored['thumb'],
                    ];
                    return 'Bild in der Mediathek gespeichert. URL: ' . $stored['url']
                        . ' — HINWEIS: Bitte sicherstellen, dass die Nutzungsrechte am Bild bestehen (Urheberrecht).';

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
