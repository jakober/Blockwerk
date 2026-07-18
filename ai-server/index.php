<?php
/**
 * Blockwerk Orange – zentraler KI-Dienst (Token-Verkauf).
 *
 * NICHT Teil der CMS-Installation! Dieses Verzeichnis wird nur auf dem
 * Server des Anbieters deployt (siehe README.md). Ohne config.php ist der
 * Dienst funktionslos.
 *
 * Endpunkte:
 *   POST /v1/chat     – reicht an die Claude-API (Anthropic Messages) weiter
 *   POST /v1/image    – reicht an die OpenAI-Images-API weiter
 *   GET  /v1/balance  – Token-Guthaben einer Lizenz
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    echo json_encode(['error' => 'Der KI-Dienst ist nicht konfiguriert.']);
    exit;
}
$config = require $configFile;

/* ---------- Datenbank (SQLite) ---------- */

require_once __DIR__ . '/index-lib.php';

function db(): PDO
{
    return aiDb();
}

function fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function license(string $key): array
{
    $key = trim($key);
    if ($key === '') {
        fail(401, 'Kein Lizenzschlüssel angegeben.');
    }
    $stmt = db()->prepare('SELECT * FROM licenses WHERE license_key = ?');
    $stmt->execute([$key]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($license === false || !(int) $license['active']) {
        fail(403, 'Lizenzschlüssel ungültig oder deaktiviert.');
    }
    return $license;
}

function balanceOf(array $license): int
{
    return max(0, (int) $license['tokens_total'] - (int) $license['tokens_used']);
}

function charge(string $key, string $type, int $tokens): int
{
    $pdo = db();
    $pdo->prepare('UPDATE licenses SET tokens_used = tokens_used + ? WHERE license_key = ?')->execute([$tokens, $key]);
    $pdo->prepare('INSERT INTO requests (license_key, type, tokens) VALUES (?, ?, ?)')->execute([$key, $type, $tokens]);
    $stmt = $pdo->prepare('SELECT * FROM licenses WHERE license_key = ?');
    $stmt->execute([$key]);
    return balanceOf($stmt->fetch(PDO::FETCH_ASSOC) ?: ['tokens_total' => 0, 'tokens_used' => 0]);
}

function rateLimit(string $key, array $config): void
{
    $limit = (int) ($config['rate_limit_per_minute'] ?? 20);
    $stmt = db()->prepare("SELECT COUNT(*) FROM requests WHERE license_key = ? AND created_at > datetime('now', '-60 seconds')");
    $stmt->execute([$key]);
    if ((int) $stmt->fetchColumn() >= $limit) {
        fail(429, 'Zu viele Anfragen – bitte kurz warten.');
    }
}

function upstream(string $url, array $headers, string $body, int $timeout = 180): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if (!is_string($response)) {
        fail(502, 'Upstream nicht erreichbar: ' . $err);
    }
    $json = json_decode($response, true);
    if (!is_array($json)) {
        fail(502, 'Ungültige Upstream-Antwort (HTTP ' . $status . ').');
    }
    if ($status >= 400) {
        $msg = $json['error']['message'] ?? ('Upstream-Fehler HTTP ' . $status);
        fail(502, is_string($msg) ? $msg : 'Upstream-Fehler.');
    }
    return $json;
}

function readJsonBody(int $maxBytes = 2000000): array
{
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1) ?: '';
    if (strlen($raw) > $maxBytes) {
        fail(413, 'Anfrage zu groß.');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        fail(400, 'Ungültiger JSON-Body.');
    }
    return $data;
}

/* ---------- Mock-Modus (für lokale Tests ohne echte API-Keys) ---------- */

function mockChat(array $messages): array
{
    $last = end($messages);
    $lastContent = is_array($last['content'] ?? null) ? $last['content'] : [];
    $lastToolResult = null;
    foreach ($lastContent as $part) {
        if (($part['type'] ?? '') === 'tool_result') {
            $lastToolResult = is_string($part['content'] ?? null)
                ? $part['content']
                : json_encode($part['content'] ?? '');
        }
    }

    $usage = ['input_tokens' => 1200, 'output_tokens' => 700];

    // Layout-Szenario: Nutzer erwähnt "footer" → get_layout → update_layout.
    $lastUserText = '';
    foreach ($messages as $message) {
        if (($message['role'] ?? '') === 'user' && is_string($message['content'] ?? null)) {
            $lastUserText = strtolower($message['content']);
        }
    }
    // News-Szenario: Nutzer erwähnt "news" → create_post.
    if (str_contains($lastUserText, 'news') && $lastToolResult === null) {
        return ['id' => 'mock-n1', 'role' => 'assistant', 'stop_reason' => 'tool_use', 'usage' => $usage, 'content' => [
            ['type' => 'tool_use', 'id' => 'toolu_mock_post', 'name' => 'create_post', 'input' => [
                'type' => 'news', 'title' => 'Unsere neue Website ist online',
                'excerpt' => 'Ab sofort präsentieren wir uns im frischen Design.',
                'body' => '<p>Wir freuen uns, unsere runderneuerte Website vorzustellen – erstellt mit dem KI-Assistenten.</p>',
            ]],
        ]];
    }
    if (str_contains($lastUserText, 'news') && $lastToolResult !== null) {
        return ['id' => 'mock-n2', 'role' => 'assistant', 'stop_reason' => 'end_turn', 'usage' => $usage, 'content' => [
            ['type' => 'text', 'text' => 'Erledigt! Ich habe den News-Beitrag „Unsere neue Website ist online" angelegt.'],
        ]];
    }

    if (str_contains($lastUserText, 'footer')) {
        if ($lastToolResult === null) {
            return ['id' => 'mock-l1', 'role' => 'assistant', 'stop_reason' => 'tool_use', 'usage' => $usage, 'content' => [
                ['type' => 'text', 'text' => 'Ich sehe mir zuerst das Layout an.'],
                ['type' => 'tool_use', 'id' => 'toolu_mock_getlayout', 'name' => 'get_layout', 'input' => ['layout_id' => 2]],
            ]];
        }
        if (str_contains($lastToolResult, 'Builder-JSON')) {
            $json = substr($lastToolResult, (int) strpos($lastToolResult, "\n") + 1);
            $builder = json_decode($json, true);
            if (is_array($builder) && is_array($builder['rows'] ?? null) && $builder['rows'] !== []) {
                $contactRow = ['columns' => [['span' => 12, 'blocks' => [
                    ['type' => 'heading', 'data' => ['text' => 'Kontakt aufnehmen', 'level' => 'h2', 'variant' => 'centered']],
                    ['type' => 'form', 'data' => ['subject' => 'Anfrage über die Website', 'button_text' => 'Nachricht senden']],
                ]]], 'style' => ['bg' => 'surface', 'pt' => 40, 'pb' => 40]];
                array_splice($builder['rows'], count($builder['rows']) - 1, 0, [$contactRow]);
                return ['id' => 'mock-l2', 'role' => 'assistant', 'stop_reason' => 'tool_use', 'usage' => $usage, 'content' => [
                    ['type' => 'tool_use', 'id' => 'toolu_mock_updlayout', 'name' => 'update_layout',
                     'input' => ['layout_id' => 2, 'builder' => $builder]],
                ]];
            }
        }
        return ['id' => 'mock-l3', 'role' => 'assistant', 'stop_reason' => 'end_turn', 'usage' => $usage, 'content' => [
            ['type' => 'text', 'text' => 'Fertig! Ich habe im Layout eine Kontakt-Sektion direkt über dem Footer eingefügt – sie erscheint jetzt auf allen Seiten.'],
        ]];
    }

    if ($lastToolResult === null) {
        // Erste Runde: Bild generieren lassen.
        return ['id' => 'mock-1', 'role' => 'assistant', 'stop_reason' => 'tool_use', 'usage' => $usage, 'content' => [
            ['type' => 'text', 'text' => 'Ich erstelle zuerst ein passendes Bild.'],
            ['type' => 'tool_use', 'id' => 'toolu_mock_img', 'name' => 'generate_image',
             'input' => ['prompt' => 'Modernes Testbild', 'filename' => 'ki-testbild']],
        ]];
    }

    if (str_contains($lastToolResult, 'uploads/')) {
        // Zweite Runde: Seite mit dem generierten Bild anlegen.
        preg_match('~[^"\s]*uploads/[^"\s]+~', $lastToolResult, $m);
        $imageUrl = $m[0] ?? '';
        return ['id' => 'mock-2', 'role' => 'assistant', 'stop_reason' => 'tool_use', 'usage' => $usage, 'content' => [
            ['type' => 'tool_use', 'id' => 'toolu_mock_page', 'name' => 'create_page', 'input' => [
                'title' => 'KI-Testseite',
                'in_menu' => 0,
                'meta_description' => 'Automatisch vom KI-Assistenten erstellte Testseite.',
                'content' => ['rows' => [
                    ['columns' => [['span' => 12, 'blocks' => [
                        ['type' => 'heading', 'data' => ['text' => 'Willkommen auf der KI-Testseite', 'level' => 'h1', 'variant' => 'accent-line']],
                    ]]]],
                    ['style' => ['bg' => 'surface', 'pt' => 30, 'pb' => 30], 'columns' => [
                        ['span' => 6, 'blocks' => [
                            ['type' => 'text', 'data' => ['html' => '<p>Dieser Inhalt wurde vom KI-Assistenten erstellt – inklusive Bild und moderner Gestaltung.</p>']],
                            ['type' => 'button', 'data' => ['text' => 'Mehr erfahren', 'url' => '#', 'style' => 'primary', 'size' => 'normal']],
                        ]],
                        ['span' => 6, 'blocks' => [
                            ['type' => 'image', 'data' => ['src' => $imageUrl, 'alt' => 'KI-Testbild', 'variant' => 'shadow']],
                        ]],
                    ]],
                ]],
            ]],
        ]];
    }

    // Dritte Runde: fertig.
    return ['id' => 'mock-3', 'role' => 'assistant', 'stop_reason' => 'end_turn', 'usage' => $usage, 'content' => [
        ['type' => 'text', 'text' => 'Fertig! Ich habe die Seite „KI-Testseite“ mit einem generierten Bild angelegt. Du findest sie unter Seiten – sag einfach Bescheid, wenn ich etwas ändern soll.'],
    ]];
}

/** 1×1-Pixel-PNG (orange) für den Mock-Bildendpunkt. */
function mockImage(): string
{
    return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIA7lD0SAAAAABJRU5ErkJggg==';
}

/* ---------- Routing ---------- */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$mock = !empty($config['mock']);

if ($method === 'GET' && str_ends_with($path, '/v1/balance')) {
    $lic = license((string) ($_GET['license_key'] ?? ''));
    echo json_encode(['ok' => true, 'balance' => balanceOf($lic), 'name' => $lic['name']], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST' && str_ends_with($path, '/v1/chat')) {
    $body = readJsonBody();
    $lic = license((string) ($body['license_key'] ?? ''));
    rateLimit($lic['license_key'], $config);
    if (balanceOf($lic) <= 0) {
        fail(402, 'Kein Token-Guthaben mehr. Bitte Guthaben aufladen.');
    }

    $messages = is_array($body['messages'] ?? null) ? $body['messages'] : [];
    if ($messages === [] || count($messages) > 60) {
        fail(400, 'Ungültiger Gesprächsverlauf.');
    }

    if ($mock) {
        $response = mockChat($messages);
    } else {
        $payload = [
            'model' => (string) ($config['model'] ?? 'claude-sonnet-5'),
            'max_tokens' => min(16000, max(1000, (int) ($body['max_tokens'] ?? 8000))),
            'system' => (string) ($body['system'] ?? ''),
            'messages' => $messages,
        ];
        if (is_array($body['tools'] ?? null) && $body['tools'] !== []) {
            $payload['tools'] = $body['tools'];
        }
        $response = upstream('https://api.anthropic.com/v1/messages', [
            'Content-Type: application/json',
            'x-api-key: ' . (string) ($config['anthropic_key'] ?? ''),
            'anthropic-version: 2023-06-01',
        ], json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '');
    }

    $tokens = (int) ($response['usage']['input_tokens'] ?? 0) + (int) ($response['usage']['output_tokens'] ?? 0);
    $balance = charge($lic['license_key'], 'chat', max(1, $tokens));
    $response['balance'] = $balance;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST' && str_ends_with($path, '/v1/image')) {
    $body = readJsonBody(200000);
    $lic = license((string) ($body['license_key'] ?? ''));
    rateLimit($lic['license_key'], $config);
    $price = (int) ($config['image_token_price'] ?? 25000);
    if (balanceOf($lic) < $price) {
        fail(402, 'Nicht genug Token-Guthaben für ein Bild (benötigt: ' . $price . ').');
    }

    $prompt = trim((string) ($body['prompt'] ?? ''));
    if ($prompt === '' || strlen($prompt) > 4000) {
        fail(400, 'Ungültiger Bild-Prompt.');
    }

    if ($mock) {
        $b64 = mockImage();
    } else {
        $response = upstream('https://api.openai.com/v1/images/generations', [
            'Content-Type: application/json',
            'Authorization: Bearer ' . (string) ($config['openai_key'] ?? ''),
        ], json_encode([
            'model' => (string) ($config['image_model'] ?? 'gpt-image-1'),
            'prompt' => $prompt,
            'size' => in_array($body['size'] ?? '', ['1024x1024', '1536x1024', '1024x1536'], true) ? $body['size'] : '1536x1024',
            'n' => 1,
        ], JSON_UNESCAPED_UNICODE) ?: '', 300);
        $b64 = (string) ($response['data'][0]['b64_json'] ?? '');
        if ($b64 === '') {
            fail(502, 'Die Bild-API hat kein Bild geliefert.');
        }
    }

    $balance = charge($lic['license_key'], 'image', $price);
    echo json_encode(['ok' => true, 'image_b64' => $b64, 'mime' => 'image/png', 'balance' => $balance], JSON_UNESCAPED_UNICODE);
    exit;
}

fail(404, 'Unbekannter Endpunkt.');
