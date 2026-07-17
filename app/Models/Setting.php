<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class Setting
{
    public static function get(string $name, string $default = ''): string
    {
        $stmt = Database::pdo()->prepare('SELECT value FROM settings WHERE name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        return $row !== false ? (string) $row['value'] : $default;
    }

    public static function set(string $name, string $value): void
    {
        Database::pdo()
            ->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')
            ->execute([$name, $value]);
    }
}
