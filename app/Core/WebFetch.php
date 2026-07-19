<?php
declare(strict_types=1);

namespace Core;

/**
 * Externe Webinhalte abrufen, damit die KI eine Seite als Vorlage ansehen und
 * einzelne Bilder in die Mediathek übernehmen kann. Mit SSRF-Schutz (keine
 * internen/privaten Adressen) und Größenbegrenzung.
 *
 * WICHTIG: Fremde Inhalte/Bilder können urheberrechtlich geschützt sein – die
 * Verantwortung für die Nutzung liegt beim Betreiber.
 */
class WebFetch
{
    private const MAX_HTML = 3_000_000;   // 3 MB
    private const MAX_IMAGE = 12_000_000; // 12 MB

    /** Nur http/https und keine internen/privaten Ziele zulassen. */
    public static function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }
        $host = $parts['host'] ?? '';
        if ($host === '') {
            return false;
        }
        // Alle aufgelösten IPs prüfen (verhindert DNS-Rebinding auf interne Netze).
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            foreach ($records as $r) {
                $ips[] = $r['ip'] ?? ($r['ipv6'] ?? '');
            }
            $byName = @gethostbynamel($host) ?: [];
            $ips = array_merge($ips, $byName);
        }
        $ips = array_filter($ips);
        if ($ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return true;
    }

    /** @return array{0:?string,1:?string,2:?array} [rawBody, contentType, error?] – intern */
    private static function get(string $url, int $maxBytes): array
    {
        if (!self::isSafeUrl($url)) {
            return [null, null, 'Diese Adresse ist nicht erlaubt (nur öffentliche http/https-Seiten).'];
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BlockwerkOrangeBot/1.0)',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_BUFFERSIZE => 65536,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static function ($ch, $dlTotal, $dlNow) use ($maxBytes) {
                return $dlNow > $maxBytes ? 1 : 0; // abbrechen, wenn zu groß
            },
        ]);
        $body = curl_exec($ch);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $body === '') {
            return [null, null, 'Die Seite konnte nicht abgerufen werden (nicht erreichbar oder zu groß).'];
        }
        if ($code >= 400) {
            return [null, null, 'Die Seite antwortete mit Fehlercode ' . $code . '.'];
        }
        return [(string) $body, $type, null];
    }

    /**
     * Seite abrufen und in eine für die KI lesbare Struktur bringen (Titel,
     * Beschreibung, Überschriften, Fließtext, Bild-URLs).
     * @return array{ok:bool,error?:string,title?:string,description?:string,text?:string,images?:array}
     */
    public static function fetchPage(string $url): array
    {
        [$body, $type, $error] = self::get($url, self::MAX_HTML);
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }

        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m)) {
            $title = self::clean($m[1]);
        }
        $description = '';
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/is', $body, $m)) {
            $description = self::clean($m[1]);
        }

        // Bild-URLs (absolut aufgelöst).
        $images = [];
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $body, $mm)) {
            foreach ($mm[1] as $src) {
                $abs = self::absoluteUrl($src, $url);
                if ($abs !== '' && !in_array($abs, $images, true)) {
                    $images[] = $abs;
                }
            }
        }
        // Auch og:image berücksichtigen.
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)) {
            $abs = self::absoluteUrl($m[1], $url);
            if ($abs !== '' && !in_array($abs, $images, true)) {
                array_unshift($images, $abs);
            }
        }

        // Überschriften + Fließtext extrahieren.
        $noScript = preg_replace('/<(script|style|noscript)[^>]*>.*?<\/\1>/is', ' ', $body) ?? $body;
        $headings = [];
        if (preg_match_all('/<h([1-3])[^>]*>(.*?)<\/h\1>/is', $noScript, $hm, PREG_SET_ORDER)) {
            foreach ($hm as $h) {
                $t = self::clean($h[2]);
                if ($t !== '') {
                    $headings[] = 'H' . $h[1] . ': ' . $t;
                }
            }
        }
        $text = self::clean(strip_tags($noScript));
        $text = mb_substr($text, 0, 6000);

        return [
            'ok' => true,
            'title' => $title,
            'description' => $description,
            'headings' => array_slice($headings, 0, 40),
            'text' => $text,
            'images' => array_slice($images, 0, 40),
        ];
    }

    /**
     * Ein Bild herunterladen (mit MIME-Prüfung).
     * @return array{ok:bool,error?:string,bytes?:string,mime?:string,ext?:string}
     */
    public static function fetchImage(string $url): array
    {
        [$body, $type, $error] = self::get($url, self::MAX_IMAGE);
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->buffer($body);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'error' => 'Die Adresse liefert kein unterstütztes Bild (' . ($mime ?: 'unbekannt') . ').'];
        }
        return ['ok' => true, 'bytes' => $body, 'mime' => $mime, 'ext' => $allowed[$mime]];
    }

    private static function clean(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /** Relative URL an der Basis-URL zu einer absoluten auflösen. */
    private static function absoluteUrl(string $src, string $base): string
    {
        $src = trim($src);
        if ($src === '' || str_starts_with($src, 'data:')) {
            return '';
        }
        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }
        $b = parse_url($base);
        if (!isset($b['scheme'], $b['host'])) {
            return '';
        }
        $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($src, '//')) {
            return $b['scheme'] . ':' . $src;
        }
        if (str_starts_with($src, '/')) {
            return $origin . $src;
        }
        $path = isset($b['path']) ? preg_replace('#/[^/]*$#', '/', $b['path']) : '/';
        return $origin . $path . $src;
    }
}
