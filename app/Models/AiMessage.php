<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

/**
 * Gespeicherter Gesprächsverlauf des KI-Assistenten – pro Backend-Nutzer.
 * Dient als Gedächtnis (frühere Anweisungen) und lässt den Chat nach dem
 * Neuladen wieder erscheinen.
 *
 * Alle Methoden sind absturzsicher: Fehlt die Tabelle (z. B. Schema noch
 * nicht migriert), wird sie einmalig angelegt; scheitert der Zugriff
 * weiterhin, degradiert die Funktion still (kein 500 im KI-Assistenten).
 */
class AiMessage
{
    private static function ensureTable(): void
    {
        Database::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS ai_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                role VARCHAR(16) NOT NULL,
                content MEDIUMTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ai_messages_user (user_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public static function add(int $userId, string $role, string $text): void
    {
        $role = $role === 'assistant' ? 'assistant' : 'user';
        $sql = 'INSERT INTO ai_messages (user_id, role, content) VALUES (?, ?, ?)';
        try {
            Database::pdo()->prepare($sql)->execute([$userId, $role, $text]);
        } catch (\Throwable) {
            try {
                self::ensureTable();
                Database::pdo()->prepare($sql)->execute([$userId, $role, $text]);
            } catch (\Throwable) {
                // still – Persistenz ist best effort, darf nie den Chat brechen.
            }
        }
    }

    /** Verlauf eines Nutzers in chronologischer Reihenfolge (max. $limit jüngste). */
    public static function recent(int $userId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT role, content FROM ai_messages WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit;
        try {
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute([$userId]);
            return array_reverse($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Throwable) {
            try {
                self::ensureTable();
            } catch (\Throwable) {
            }
            return [];
        }
    }

    public static function clear(int $userId): void
    {
        try {
            Database::pdo()->prepare('DELETE FROM ai_messages WHERE user_id = ?')->execute([$userId]);
        } catch (\Throwable) {
        }
    }
}
