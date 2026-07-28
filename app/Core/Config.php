<?php
declare(strict_types=1);

namespace Core;

/**
 * Zugriff auf config/config.php (rein datei-basiert, funktioniert auch ohne
 * Datenbank). Die Datei enthält den `db`-Block mit den Zugangsdaten; ihr
 * Fehlen bedeutet „noch nicht installiert" und leitet auf /install um.
 */
class Config
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = is_file(CONFIG_FILE) ? (array) (require CONFIG_FILE) : [];
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    /** Wert aus einem Untergruppen-Array, z. B. Config::sub('ai','license_key'). */
    public static function sub(string $group, string $key, mixed $default = null): mixed
    {
        $g = self::all()[$group] ?? null;
        return is_array($g) ? ($g[$key] ?? $default) : $default;
    }

    /** Ganze Konfiguration schreiben (überschreibt config/config.php). */
    public static function save(array $config): bool
    {
        $php = "<?php\nreturn " . var_export($config, true) . ";\n";
        $dir = dirname(CONFIG_FILE);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }
        if (file_put_contents(CONFIG_FILE, $php) === false) {
            return false;
        }
        self::$cache = $config;
        // Opcache für die neu geschriebene Datei leeren, damit der nächste
        // Request den aktuellen Stand liest.
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(CONFIG_FILE, true);
        }
        return true;
    }
}
