<?php
declare(strict_types=1);

namespace Core;

use PDO;

class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $config = require CONFIG_FILE;
            $db = $config['db'];
            self::$pdo = self::connect($db['host'], (int) $db['port'], $db['name'], $db['user'], $db['pass']);
        }
        return self::$pdo;
    }

    public static function connect(string $host, int $port, string $name, string $user, string $pass): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function createSchema(PDO $pdo): void
    {
        $statements = [
            'CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(64) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS settings (
                name VARCHAR(64) NOT NULL PRIMARY KEY,
                value TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS layouts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                html MEDIUMTEXT NOT NULL,
                design TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                tkey VARCHAR(64) NOT NULL UNIQUE,
                html MEDIUMTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS pages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                parent_id INT UNSIGNED NULL,
                title VARCHAR(200) NOT NULL,
                slug VARCHAR(200) NOT NULL UNIQUE,
                layout_id INT UNSIGNED NULL,
                in_menu TINYINT(1) NOT NULL DEFAULT 0,
                menu_order INT NOT NULL DEFAULT 0,
                published TINYINT(1) NOT NULL DEFAULT 1,
                content MEDIUMTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_parent (parent_id),
                INDEX idx_menu (in_menu, menu_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS media (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                path VARCHAR(255) NOT NULL,
                mime VARCHAR(100) NOT NULL,
                size INT UNSIGNED NOT NULL DEFAULT 0,
                width INT UNSIGNED NULL,
                height INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS posts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                type ENUM(\'news\', \'event\') NOT NULL DEFAULT \'news\',
                title VARCHAR(200) NOT NULL,
                slug VARCHAR(200) NOT NULL UNIQUE,
                excerpt TEXT NULL,
                body MEDIUMTEXT NULL,
                image VARCHAR(255) NULL,
                published TINYINT(1) NOT NULL DEFAULT 1,
                published_at DATETIME NULL,
                start_at DATETIME NULL,
                end_at DATETIME NULL,
                location VARCHAR(200) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_type (type, published),
                INDEX idx_start (start_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS fonts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                family VARCHAR(100) NOT NULL,
                folder VARCHAR(120) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS page_versions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                page_id INT UNSIGNED NOT NULL,
                title VARCHAR(200) NOT NULL,
                content MEDIUMTEXT NULL,
                username VARCHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_page (page_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS form_entries (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                page_title VARCHAR(200) NULL,
                data TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS redirects (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                from_slug VARCHAR(200) NOT NULL UNIQUE,
                to_slug VARCHAR(200) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        ];

        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }

        self::migrate($pdo);
    }

    /**
     * Spalten-Migrationen für bestehende Installationen – läuft bei jeder
     * Installation UND bei jedem Update (Updater ruft createSchema auf).
     * Neue Spalten hier ergänzen, nicht nur im CREATE TABLE.
     */
    private static function migrate(PDO $pdo): void
    {
        self::ensureColumn($pdo, 'pages', 'meta_title', 'VARCHAR(200) NULL');
        self::ensureColumn($pdo, 'pages', 'meta_description', 'TEXT NULL');
        self::ensureColumn($pdo, 'pages', 'noindex', 'TINYINT(1) NOT NULL DEFAULT 0');
        self::ensureColumn($pdo, 'pages', 'deleted_at', 'DATETIME NULL');
        self::ensureColumn($pdo, 'pages', 'lang', "VARCHAR(5) NOT NULL DEFAULT 'de'");
        self::ensureColumn($pdo, 'pages', 'is_global', 'TINYINT(1) NOT NULL DEFAULT 0');
        self::ensureColumn($pdo, 'users', 'role', "VARCHAR(20) NOT NULL DEFAULT 'admin'");
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }
}
