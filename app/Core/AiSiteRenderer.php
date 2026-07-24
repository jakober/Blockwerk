<?php
declare(strict_types=1);

namespace Core;

/**
 * Liefert im KI-Modus die von der KI erzeugten statischen HTML-Seiten aus.
 * „Schöne" URLs werden auf Dateien in public/ai-site/ abgebildet
 * (`/` → index.html, `/kontakt` → kontakt.html, `/blog/` → blog/index.html).
 * Real existierende Assets (CSS/JS/Bilder) liefert der Webserver ohnehin direkt.
 */
class AiSiteRenderer
{
    public static function serve(string $path): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        $rel = trim($path, '/');
        if ($rel === '') {
            $rel = 'index';
        }

        $candidates = [];
        if (str_ends_with($rel, '.html') || str_ends_with($rel, '.htm')) {
            $candidates[] = $rel;
        } else {
            $candidates[] = $rel . '.html';
            $candidates[] = $rel . '/index.html';
        }

        foreach ($candidates as $c) {
            $full = AiSite::safePath($c);
            if ($full !== null && is_file($full)) {
                readfile($full);
                return;
            }
        }

        http_response_code(404);
        $custom = AiSite::safePath('404.html');
        if ($custom !== null && is_file($custom)) {
            readfile($custom);
            return;
        }
        $home = e(rtrim(App::base(), '/') . '/');
        echo '<!doctype html><meta charset="utf-8"><title>Nicht gefunden</title>'
            . '<div style="font-family:system-ui,-apple-system,sans-serif;max-width:520px;margin:16vh auto;text-align:center;color:#334155">'
            . '<h1 style="font-size:3rem;margin:.2em 0">404</h1><p>Diese Seite gibt es (noch) nicht.</p>'
            . '<p><a href="' . $home . '" style="color:#ea580c;font-weight:600">Zur Startseite</a></p></div>';
    }
}
