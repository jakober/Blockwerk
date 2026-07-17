<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

/** Gespeicherte Formular-Einsendungen (zusätzlich zum E-Mail-Versand). */
class FormEntry
{
    public static function all(int $limit = 300): array
    {
        return Database::pdo()
            ->query('SELECT * FROM form_entries ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit))
            ->fetchAll();
    }

    public static function create(?string $pageTitle, array $data): void
    {
        Database::pdo()->prepare('INSERT INTO form_entries (page_title, data) VALUES (?, ?)')
            ->execute([$pageTitle, json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}']);
    }

    public static function countUnread(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM form_entries WHERE is_read = 0')->fetchColumn();
    }

    public static function markAllRead(): void
    {
        Database::pdo()->exec('UPDATE form_entries SET is_read = 1 WHERE is_read = 0');
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM form_entries WHERE id = ?')->execute([$id]);
    }
}
