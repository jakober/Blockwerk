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
    // config/ und uploads/ werden nie überschrieben.
    private const PROTECTED = ['config/', 'public/uploads/', 'public/ai-site/', '.git/'];

    // Der zentrale KI-Dienst (ai-server/) wird nur auf den Domains des
    // Anbieters mit ausgeliefert – Kunden-Installationen erhalten ihn nicht.
    private const VENDOR_HOSTS = ['blockwerk-orange.de'];

    public static function isVendorHost(): bool
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '') ?? '');
        return in_array($host, self::VENDOR_HOSTS, true);
    }

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

    public static function remoteVersion(int $timeout = 120): ?string
    {
        $url = self::versionUrl();
        // Zuerst die rohe Datei abrufen: kein Rate-Limit, sehr zuverlässig. Ein
        // Cache-Buster (?t=…) umgeht das ~5-Minuten-CDN-Caching so weit wie möglich.
        $bust = (str_contains($url, '?') ? '&' : '?') . 't=' . time();
        $version = self::parseVersion(self::fetch($url . $bust, [], $timeout));
        if ($version !== null) {
            return $version;
        }
        // Fallback: GitHub-API (immer frisch, aber Rate-Limit 60/h und je nach
        // Server/Proxy nicht immer erreichbar).
        if (preg_match('#^https://raw\.githubusercontent\.com/([^/]+)/([^/]+)/(.+)/VERSION$#', $url, $m)) {
            $api = 'https://api.github.com/repos/' . $m[1] . '/' . $m[2] . '/contents/VERSION?ref=' . rawurlencode($m[3]);
            $version = self::parseVersion(self::fetch($api, ['Accept: application/vnd.github.raw'], $timeout));
        }
        return $version;
    }

    /** Trimmt und akzeptiert nur eine echte Versionsnummer (x.y.z). */
    private static function parseVersion(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $version = trim($raw);
        return preg_match('/^\d+\.\d+\.\d+$/', $version) ? $version : null;
    }

    /**
     * Verfügbare Version aus dem Cache (in den Einstellungen). Wird bei Bedarf
     * mit kurzem Timeout aufgefrischt, damit Seitenaufrufe nicht hängen.
     */
    public static function cachedRemoteVersion(bool $force = false): ?string
    {
        $cached = trim((string) \Models\Setting::get('update_remote', ''));
        $checkedAt = (int) \Models\Setting::get('update_checked_at', '0');
        $ttl = 6 * 3600; // höchstens alle 6 Stunden online nachsehen
        if (!$force && $cached !== '' && (time() - $checkedAt) < $ttl) {
            return $cached !== '' ? $cached : null;
        }
        $remote = self::remoteVersion($force ? 20 : 4);
        \Models\Setting::set('update_checked_at', (string) time());
        \Models\Setting::set('update_checked_version', self::currentVersion());
        if ($remote !== null) {
            \Models\Setting::set('update_remote', $remote);
            return $remote;
        }
        // Bei Fehler den letzten bekannten Wert behalten (nicht bei jedem Aufruf neu versuchen).
        return $cached !== '' ? $cached : null;
    }

    /**
     * Prüft bei Fälligkeit online auf eine neuere Version und gibt sie zurück
     * (sonst null). Gedacht für den Hintergrund-Aufruf per XHR NACH dem Laden
     * der Seite: dort darf der Netzabruf ruhig ein paar Sekunden dauern, weil er
     * den Seitenaufbau selbst nicht blockiert. So hängt kein Klick im Backend.
     */
    public static function autoCheck(): ?string
    {
        $cached = self::cachedRemote();
        $checkedAt = (int) \Models\Setting::get('update_checked_at', '0');
        $checkedVer = (string) \Models\Setting::get('update_checked_version', '');
        $current = self::currentVersion();
        // Alle 2 Minuten erneut online nachsehen (kleiner Hintergrund-Abruf einer
        // ~7-Byte-Datei, blockiert die Seite nicht). Solange noch gar kein Wert
        // bekannt ist, alle 60 s. So erscheint ein neues Release zügig – nicht
        // erst nach Stunden.
        $ttl = $cached !== null ? 120 : 60;
        $stale = (time() - $checkedAt) >= $ttl;
        // Hat sich die installierte Version seit der letzten Prüfung geändert
        // (z. B. gerade ein Update eingespielt), SOFORT neu prüfen – sonst würde
        // der alte Cache bis zu 6 h lang „aktuell" behaupten.
        $versionChanged = $checkedVer !== $current;
        if ($stale || $versionChanged) {
            \Models\Setting::set('update_checked_at', (string) time());
            \Models\Setting::set('update_checked_version', $current);
            try {
                $remote = self::remoteVersion(15);
                if ($remote !== null) {
                    \Models\Setting::set('update_remote', $remote);
                }
            } catch (\Throwable) {
                // Ignorieren – beim nächsten Fälligkeitsfenster erneut versuchen.
            }
        }
        return self::updateAvailable();
    }

    /** Reiner Cache-Lesezugriff (kein Netz) – zeigt die zuletzt bekannte Version. */
    public static function cachedRemote(): ?string
    {
        $cached = trim((string) \Models\Setting::get('update_remote', ''));
        return $cached !== '' ? $cached : null;
    }

    /**
     * Diagnose: prüft, ob die Versions-Quellen (GitHub API + raw) erreichbar sind
     * und was sie liefern. Nur für die Fehlersuche (Status-Endpunkt ?force=1).
     * Gibt je Quelle HTTP-Status, kurzen Body-Auszug und ggf. curl-Fehler zurück.
     */
    public static function diagnose(): array
    {
        $probe = static function (string $url, array $headers = []): array {
            if (!function_exists('curl_init')) {
                return ['url' => $url, 'error' => 'curl fehlt'];
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'Blockwerk-Updater',
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            return [
                'url' => $url,
                'http' => $http,
                'body' => is_string($body) ? substr(trim($body), 0, 60) : null,
                'curl_error' => $err !== '' ? $err : null,
            ];
        };

        $versionUrl = self::versionUrl();
        $out = ['versionUrl' => $versionUrl, 'raw' => $probe($versionUrl)];
        if (preg_match('#^https://raw\.githubusercontent\.com/([^/]+)/([^/]+)/(.+)/VERSION$#', $versionUrl, $m)) {
            $api = 'https://api.github.com/repos/' . $m[1] . '/' . $m[2] . '/contents/VERSION?ref=' . rawurlencode($m[3]);
            $out['api'] = $probe($api, ['Accept: application/vnd.github.raw']);
        }
        return $out;
    }

    /** Gibt die verfügbare neuere Version zurück (nur Cache-Lesen, kein Netz), sonst null. */
    public static function updateAvailable(): ?string
    {
        $cached = trim((string) \Models\Setting::get('update_remote', ''));
        if ($cached === '') {
            return null;
        }
        return version_compare($cached, self::currentVersion(), '>') ? $cached : null;
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
            if (str_starts_with($relative, 'ai-server/') && !self::isVendorHost()) {
                continue; // KI-Dienst nur für den Anbieter selbst
            }
            // Eine vorhandene Dienst-Konfiguration nie überschreiben.
            if ($relative === 'ai-server/config.php' || $relative === 'ai-server/data.sqlite') {
                continue;
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

        // Update-Cache leeren, damit nach dem Update sofort wieder online geprüft
        // wird (die neue installierte Version könnte selbst schon veraltet sein).
        \Models\Setting::set('update_remote', '');
        \Models\Setting::set('update_checked_at', '0');
        \Models\Setting::set('update_checked_version', '');

        return null;
    }

    public static function fetch(string $url, array $headers = [], int $timeout = 120): ?string
    {
        $ua = 'Blockwerk-Updater';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => max(2, min($timeout, 10)),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => $ua,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $data = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return is_string($data) && $status < 400 ? $data : null;
        }
        $headerLines = implode("\r\n", array_merge(['User-Agent: ' . $ua], $headers));
        $context = stream_context_create(['http' => ['timeout' => $timeout, 'header' => $headerLines]]);
        $data = @file_get_contents($url, false, $context);
        return $data !== false ? $data : null;
    }
}
