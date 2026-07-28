<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Ai;
use Models\Page;

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
            'buyUrl' => Ai::buyUrl(),
            'history' => \Models\AiMessage::recent((int) ($_SESSION['user_id'] ?? 0)),
        ]);
    }

    /** POST /admin/ai/plan – zerlegt die Anfrage in Schritte (führt nichts aus). */
    public function plan(): void
    {
        header('Content-Type: application/json');
        set_time_limit(120);

        $input = json_decode(file_get_contents('php://input') ?: '', true);
        $messages = self::messagesFromInput(is_array($input['messages'] ?? null) ? $input['messages'] : []);
        if ($messages === []) {
            echo json_encode(['ok' => false, 'error' => 'Keine Nachricht übermittelt.']);
            return;
        }
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        session_release(); // Session früh freigeben (blockiert sonst parallele Requests).
        $newUserText = (string) (end($messages)['content'] ?? '');

        $result = \Core\CmsAgent::plan($messages);
        if ($result['ok'] && $userId > 0 && $result['steps'] !== []) {
            $lines = [];
            foreach ($result['steps'] as $i => $s) {
                $lines[] = ($i + 1) . '. ' . $s['title'] . ($s['detail'] !== '' ? ' – ' . $s['detail'] : '');
            }
            $this->saveTurn($userId, $newUserText, "Plan:\n" . implode("\n", $lines));
        }
        echo json_encode([
            'ok' => $result['ok'],
            'steps' => $result['steps'],
            'intro' => $result['intro'],
            'balance' => $result['balance'],
            'error' => $result['error'],
        ], JSON_UNESCAPED_UNICODE);
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

    /** POST /admin/ai/chat – führt einen kompletten Assistenten-Durchlauf aus. */
    public function chat(): void
    {
        header('Content-Type: application/json');
        set_time_limit(300);

        $input = json_decode(file_get_contents('php://input') ?: '', true);
        $messages = self::messagesFromInput(is_array($input['messages'] ?? null) ? $input['messages'] : []);
        if ($messages === []) {
            echo json_encode(['ok' => false, 'error' => 'Keine Nachricht übermittelt.']);
            return;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        \Core\CmsAgent::useAuthor((string) ($_SESSION['username'] ?? ''));
        // Session früh freigeben, damit das Frontend während der KI-Anfrage nicht blockiert.
        session_release();
        $newUserText = (string) (end($messages)['content'] ?? '');

        $result = \Core\CmsAgent::run($messages, !empty($input['fast']));
        if ($result['ok']) {
            $this->saveTurn($userId, $newUserText, (string) $result['text']);
        }
        echo json_encode([
            'ok' => $result['ok'],
            'text' => $result['text'],
            'error' => $result['error'],
            'actions' => $result['actions'],
            'balance' => $result['balance'],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Gesprächsverlauf des Clients in das Format der KI übersetzen: nur
     * Text-Turns, auf die letzten 16 begrenzt, muss mit einer Nutzerfrage enden.
     */
    private static function messagesFromInput(array $history): array
    {
        $messages = [];
        foreach (array_slice($history, -16) as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $text = trim((string) ($turn['text'] ?? ''));
            if ($text !== '' && strlen($text) < 20000) {
                $messages[] = ['role' => $role, 'content' => $text];
            }
        }
        return ($messages === [] || end($messages)['role'] !== 'user') ? [] : $messages;
    }
}
