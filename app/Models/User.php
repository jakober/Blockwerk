<?php
declare(strict_types=1);

namespace Models;

use Core\Database;

class User
{
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public static function create(\PDO $pdo, string $username, string $password): void
    {
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    }

    public static function all(): array
    {
        return Database::pdo()->query('SELECT id, username, created_at FROM users ORDER BY username')->fetchAll();
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public static function createUser(string $username, string $password): void
    {
        self::create(Database::pdo(), $username, $password);
    }

    public static function updateName(int $id, string $username): void
    {
        Database::pdo()->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$username, $id]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        Database::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
}
