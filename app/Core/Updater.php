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

    /** Quelle für die Standard-Adressen (auch für den Zugriff auf ein privates Repository). */
    private const REPO = 'jakober/Blockwerk';
    private const BRANCH = 'main';

    /** Diese Pfade werden beim Update niemals überschrieben. */
    // config/ und uploads/ werden nie überschrieben.
    private const PROTECTED = ['config/', 'public/uploads/', '.git/'];

    public static function currentVersion(): string
    {
        $file = BASE_PATH . '/VERSION';
        return is_file($file) ? trim((string) file_get_contents($file)) : '0.0.0';
    }

    /**
     * Zugriffs-Token für ein privates Repository (Einstellung `update_token`,
     * GitHub „Fine-grained token" mit Leserecht auf Inhalte). Ist eines
     * hinterlegt, laufen Versions- und Paket-Abruf über die GitHub-API – nur so
     * sind die Dateien eines privaten Repositorys erreichbar.
     */
    public static function token(): string
    {
        try {
            return trim((string) \Models\Setting::get('update_token', ''));
        } catch (\Throwable) {
            return '';
        }
    }

    public static function zipUrl(): string
    {
        $url = trim((string) \Models\Setting::get('update_zip_url', ''));
        if ($url !== '') {
            return $url;
        }
        return self::token() !== ''
            ? 'https://api.github.com/repos/' . self::REPO . '/zipball/' . self::BRANCH
            : self::DEFAULT_ZIP_URL;
    }

    public static function versionUrl(): string
    {
        $url = trim((string) \Models\Setting::get('update_version_url', ''));
        if ($url !== '') {
            return $url;
        }
        return self::token() !== ''
            ? 'https://api.github.com/repos/' . self::REPO . '/contents/VERSION?ref=' . self::BRANCH
            : self::DEFAULT_VERSION_URL;
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

    /**
     * Zusätzliche Kopfzeilen für GitHub-Abrufe: Token (privates Repository) und
     * das Roh-Format der Inhalts-API. Für fremde Adressen wird nie ein Token
     * mitgeschickt.
     *
     * @param array<int,string> $existing schon gesetzte Kopfzeilen
     * @return array<int,string>
     */
    private static function authHeaders(string $url, array $existing = []): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $github = in_array($host, ['api.github.com', 'github.com', 'raw.githubusercontent.com', 'codeload.github.com'], true);
        if (!$github) {
            return [];
        }
        $add = [];
        $token = self::token();
        $hasAuth = false;
        foreach ($existing as $line) {
            if (stripos((string) $line, 'authorization:') === 0) {
                $hasAuth = true;
            }
        }
        if ($token !== '' && !$hasAuth) {
            $add[] = 'Authorization: Bearer ' . $token;
        }
        // Die Inhalts-API liefert ohne diesen Wunsch JSON statt der reinen Datei.
        if ($host === 'api.github.com' && str_contains($url, '/contents/')) {
            $hasAccept = false;
            foreach ($existing as $line) {
                if (stripos((string) $line, 'accept:') === 0) {
                    $hasAccept = true;
                }
            }
            if (!$hasAccept) {
                $add[] = 'Accept: application/vnd.github.raw';
            }
        }
        return $add;
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
                CURLOPT_HTTPHEADER => array_merge($headers, self::authHeaders($url, $headers)),
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
        $out = [
            'versionUrl' => $versionUrl,
            'token' => self::token() !== '' ? 'gesetzt' : 'keins',
            'raw' => $probe($versionUrl),
        ];
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
            if ($content === false || !self::writeUpdateFile($destination, $content)) {
                $zip->close();
                unlink($tmp);
                $hint = is_file($destination)
                    ? 'Die Datei gehört vermutlich einem anderen Benutzer als dem Webserver. Setze die Schreibrechte (bzw. den Eigentümer) für die Installationsdateien, z. B. per FTP „Schreiben" erlauben oder auf dem Server: chown -R <webserver-user> und chmod -R u+rw.'
                    : 'Das Verzeichnis konnte nicht beschrieben werden – bitte Schreibrechte für den übergeordneten Ordner prüfen.';
                return 'Die Datei "' . $relative . '" konnte nicht geschrieben werden. ' . $hint;
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

    /**
     * Schreibt eine Update-Datei robust. Scheitert das direkte Überschreiben (die
     * vorhandene Datei gehört z. B. einem anderen Benutzer und ist nicht
     * beschreibbar), wird versucht, die Rechte zu setzen und – falls das
     * Verzeichnis beschreibbar ist – die Datei zu löschen und neu anzulegen.
     * Das Löschen hängt nur von den Rechten des Ordners ab, nicht der Datei,
     * und behebt so den häufigsten „konnte nicht geschrieben werden"-Fall.
     */
    private static function writeUpdateFile(string $destination, string $content): bool
    {
        if (@file_put_contents($destination, $content) !== false) {
            return true;
        }
        if (is_file($destination)) {
            @chmod($destination, 0644);
            if (@file_put_contents($destination, $content) !== false) {
                return true;
            }
            if (@unlink($destination) && @file_put_contents($destination, $content) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function fetch(string $url, array $headers = [], int $timeout = 120): ?string
    {
        $ua = 'Blockwerk-Updater';
        $headers = array_merge($headers, self::authHeaders($url, $headers));
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
