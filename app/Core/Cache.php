<?php
declare(strict_types=1);

namespace Core;

use Models\Setting;

/**
 * Einfacher Datei-Cache für fertig gerenderte Seiten (Einstellungen →
 * "Seiten-Cache"). Nur für anonyme GET-Anfragen ohne Query-String; Seiten
 * mit Formularen (CSRF-Token) werden nie gecacht. Jede Änderung im
 * Admin-Bereich leert den Cache automatisch.
 */
class Cache
{
    private const TTL = 3600;

    public static function enabled(): bool
    {
        return Setting::get('cache_enabled', '0') === '1';
    }

    public static function get(string $path): ?string
    {
        $file = self::file($path);
        if (!is_file($file) || filemtime($file) < time() - self::TTL) {
            return null;
        }
        $html = file_get_contents($file);
        return $html !== false ? $html : null;
    }

    public static function put(string $path, string $html): void
    {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return;
        }
        @file_put_contents(self::file($path), $html);
    }

    public static function clear(): void
    {
        foreach (glob(self::dir() . '/*.html') ?: [] as $file) {
            @unlink($file);
        }
    }

    private static function dir(): string
    {
        return BASE_PATH . '/cache/pages';
    }

    private static function file(string $path): string
    {
        return self::dir() . '/' . md5($path) . '.html';
    }
}
