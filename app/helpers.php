<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = Core\App::base();
    return $base . '/' . ltrim($path, '/');
}

/**
 * Installierte CMS-Version (aus der VERSION-Datei), z. B. für Cache-Busting.
 */
function cms_version(): string
{
    static $version = null;
    if ($version === null) {
        $file = dirname(__DIR__) . '/VERSION';
        $version = is_file($file) ? trim((string) file_get_contents($file)) : '0';
    }
    return $version !== '' ? $version : '0';
}

/**
 * Asset-URL mit Versions-Parameter: nach jedem Update laden Browser
 * CSS/JS automatisch frisch statt aus dem Cache.
 */
function asset(string $path): string
{
    return url($path) . '?v=' . rawurlencode(cms_version());
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/**
 * flash('success', 'Text') setzt eine Meldung, flash() liest sie aus und löscht sie.
 */
function flash(?string $type = null, ?string $message = null): ?array
{
    if ($type !== null) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }
    $data = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $data;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Ungültiges oder abgelaufenes Formular-Token. Bitte zurückgehen und erneut versuchen.');
    }
}

function format_date_de(?string $datetime, bool $withTime = false): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    $months = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli',
        'August', 'September', 'Oktober', 'November', 'Dezember'];
    $out = date('j', $ts) . '. ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    if ($withTime) {
        $out .= ', ' . date('H:i', $ts) . ' Uhr';
    }
    return $out;
}

/** Konfigurierte Sprachen (erste = Standardsprache). */
function cms_langs(): array
{
    static $langs = null;
    if ($langs === null) {
        $raw = \Models\Setting::get('languages', 'de');
        $langs = array_values(array_filter(array_map(
            static fn (string $l): string => strtolower(trim($l)),
            explode(',', $raw)
        ), static fn (string $l): bool => preg_match('/^[a-z]{2}$/', $l) === 1));
        if ($langs === []) {
            $langs = ['de'];
        }
    }
    return $langs;
}

function cms_default_lang(): string
{
    return cms_langs()[0];
}

/** Öffentliche URL einer Seite (mit Sprach-Präfix für Nicht-Standardsprachen). */
function page_url(array $page): string
{
    $lang = (string) ($page['lang'] ?? cms_default_lang());
    $prefix = $lang !== cms_default_lang() ? '/' . $lang : '';
    return url($prefix . '/' . $page['slug']);
}

function slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-');
}
