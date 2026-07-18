<?php
declare(strict_types=1);

namespace Core;

use ZipArchive;

/**
 * Aktualisiert eine bestehende Installation aus dem Update-Paket (ZIP):
 * herunterladen, entpacken, Dateien überschreiben – Konfiguration und
 * Uploads bleiben unangetastet. Danach wird das Datenbankschema ergänzt
 * (CREATE TABLE IF NOT EXISTS für neue Tabellen).
 */
class Updater
{
    public const DEFAULT_ZIP_URL = 'https://github.com/jakober/Blockwerk/archive/refs/heads/main.zip';
    public const DEFAULT_VERSION_URL = 'https://raw.githubusercontent.com/jakober/Blockwerk/main/VERSION';

    /** Diese Pfade werden beim Update niemals überschrieben. */
    private const PROTECTED = ['config/', 'public/uploads/', '.git/'];

    public static function currentVersion(): string
    {
        $file = BASE_PATH . '/VERSION';
        return is_file($file) ? trim((string) file_get_contents($file)) : '0.0.0';
    }

    public static function zipUrl(): string
    {
        $url = \Models\Setting::get('update_zip_url', '');
        return $url !== '' ? $url : self::DEFAULT_ZIP_URL;
    }

    public static function versionUrl(): string
    {
        $url = \Models\Setting::get('update_version_url', '');
        return $url !== '' ? $url : self::DEFAULT_VERSION_URL;
    }

    public static function remoteVersion(): ?string
    {
        // raw.githubusercontent.com cached bis zu 5 Minuten (auch mit
        // Query-Parametern). Für GitHub-URLs deshalb zuerst die API fragen –
        // sie liefert immer den frischen Stand.
        $url = self::versionUrl();
        $raw = null;
        if (preg_match('#^https://raw\.githubusercontent\.com/([^/]+)/([^/]+)/(.+)/VERSION$#', $url, $m)) {
            $api = 'https://api.github.com/repos/' . $m[1] . '/' . $m[2] . '/contents/VERSION?ref=' . rawurlencode($m[3]);
            $apiRaw = self::fetch($api, ['Accept: application/vnd.github.raw']);
            // Nur übernehmen, wenn die Antwort wirklich wie eine Version aussieht.
            if ($apiRaw !== null && preg_match('/^\d+\.\d+\.\d+\s*$/', $apiRaw)) {
                $raw = $apiRaw;
            }
        }
        $raw ??= self::fetch($url);
        if ($raw === null) {
            return null;
        }
        $version = trim($raw);
        return preg_match('/^\d+\.\d+\.\d+$/', $version) ? $version : null;
    }

    /** Führt das Update aus. Gibt null (Erfolg) oder eine Fehlermeldung zurück. */
    public static function apply(): ?string
    {
        if (!class_exists('ZipArchive')) {
            return 'Die PHP-Erweiterung "zip" fehlt auf diesem Server.';
        }

        $data = self::fetch(self::zipUrl());
        if ($data === null) {
            return 'Das Update-Paket konnte nicht heruntergeladen werden. Ist die Paket-URL erreichbar?';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cms-update-') ?: BASE_PATH . '/update-tmp.zip';
        if (file_put_contents($tmp, $data) === false) {
            return 'Das Update-Paket konnte nicht zwischengespeichert werden.';
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            unlink($tmp);
            return 'Das heruntergeladene Paket ist kein gültiges ZIP-Archiv.';
        }

        // GitHub-Archive haben einen Wurzelordner (z. B. Cms-main/) – erkennen und strippen.
        $first = (string) ($zip->getNameIndex(0) ?: '');
        $root = str_contains($first, '/') ? explode('/', $first)[0] . '/' : '';
        if ($root !== '') {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (!str_starts_with((string) $zip->getNameIndex($i), $root)) {
                    $root = '';
                    break;
                }
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $relative = $root !== '' ? substr($name, strlen($root)) : $name;
            if ($relative === '' || str_contains($relative, '..')) {
                continue;
            }
            foreach (self::PROTECTED as $protected) {
                if (str_starts_with($relative, $protected)) {
                    continue 2;
                }
            }
            $destination = BASE_PATH . '/' . $relative;
            if (str_ends_with($name, '/')) {
                if (!is_dir($destination)) {
                    mkdir($destination, 0755, true);
                }
                continue;
            }
            $dir = dirname($destination);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $content = $zip->getFromIndex($i);
            if ($content === false || file_put_contents($destination, $content) === false) {
                $zip->close();
                unlink($tmp);
                return 'Die Datei "' . $relative . '" konnte nicht geschrieben werden – bitte Schreibrechte prüfen.';
            }
        }
        $zip->close();
        unlink($tmp);

        // Neue Tabellen anlegen (bestehende bleiben unberührt).
        Database::createSchema(Database::pdo());

        return null;
    }

    public static function fetch(string $url, array $headers = []): ?string
    {
        $ua = 'Blockwerk-Updater';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_USERAGENT => $ua,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $data = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return is_string($data) && $status < 400 ? $data : null;
        }
        $headerLines = implode("\r\n", array_merge(['User-Agent: ' . $ua], $headers));
        $context = stream_context_create(['http' => ['timeout' => 120, 'header' => $headerLines]]);
        $data = @file_get_contents($url, false, $context);
        return $data !== false ? $data : null;
    }
}
