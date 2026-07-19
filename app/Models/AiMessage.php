<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

/**
 * Gespeicherter Gesprächsverlauf des KI-Assistenten – pro Backend-Nutzer.
 * Dient als Gedächtnis (frühere Anweisungen) und lässt den Chat nach dem
 * Neuladen wieder erscheinen.
 */
class AiMessage
{
    public static function add(int $userId, string $role, string $text): void
    {
        $role = $role === 'assistant' ? 'assistant' : 'user';
        Database::pdo()
            ->prepare('INSERT INTO ai_messages (user_id, role, content) VALUES (?, ?, ?)')
            ->execute([$userId, $role, $text]);
    }

    /** Verlauf eines Nutzers in chronologischer Reihenfolge (max. $limit jüngste). */
    public static function recent(int $userId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = Database::pdo()->prepare(
            'SELECT role, content FROM ai_messages WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit
        );
        $stmt->execute([$userId]);
        return array_reverse($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public static function clear(int $userId): void
    {
        Database::pdo()->prepare('DELETE FROM ai_messages WHERE user_id = ?')->execute([$userId]);
    }
}
