<?php
declare(strict_types=1);

namespace Controllers;

use Core\Renderer;
use Models\Page;
use Models\Setting;

class SiteController
{
    public function home(): void
    {
        $homeId = (int) Setting::get('home_page', '0');
        $page = $homeId > 0 ? Page::find($homeId) : null;

        if ($page === null || !(int) $page['published']) {
            $pages = array_filter(Page::all(), fn (array $p) => (int) $p['published'] === 1);
            $page = $pages !== [] ? reset($pages) : null;
        }

        if ($page === null) {
            $this->notFound();
        }

        echo (new Renderer())->renderPage($page);
    }

    public function show(string $slug): void
    {
        $page = Page::findBySlug($slug);
        if ($page === null || !(int) $page['published']) {
            // Slug geändert? Automatische 301-Weiterleitung.
            $target = \Models\Redirect::find($slug);
            if ($target !== null && Page::findBySlug($target) !== null) {
                header('Location: ' . url('/' . $target), true, 301);
                exit;
            }
            $this->notFound();
        }
        // Fremdsprachige Seite unter Stammadresse → auf Sprach-URL umleiten.
        if (($page['lang'] ?? cms_default_lang()) !== cms_default_lang()) {
            header('Location: ' . page_url($page), true, 301);
            exit;
        }
        echo (new Renderer())->renderPage($page);
    }

    /* ---------- Mehrsprachigkeit ---------- */

    public function homeLang(string $lang): void
    {
        Renderer::$lang = $lang;
        $homeId = (int) Setting::get('home_page_' . $lang, '0');
        $page = $homeId > 0 ? Page::find($homeId) : null;
        if ($page === null || !(int) $page['published'] || ($page['lang'] ?? '') !== $lang) {
            $pages = array_filter(Page::all(), fn (array $p) => (int) $p['published'] === 1 && ($p['lang'] ?? '') === $lang);
            $page = $pages !== [] ? reset($pages) : null;
        }
        if ($page === null) {
            $this->notFound();
        }
        echo (new Renderer())->renderPage($page);
    }

    public function showLang(string $slug, string $lang): void
    {
        Renderer::$lang = $lang;
        $page = Page::findBySlug($slug);
        if ($page === null || !(int) $page['published'] || ($page['lang'] ?? '') !== $lang) {
            $this->notFound();
        }
        echo (new Renderer())->renderPage($page);
    }

    /* ---------- Suche ---------- */

    public function search(): void
    {
        $query = trim($_GET['q'] ?? '');
        $html = '<div class="cms-search-page"><h1 class="cms-heading">Suche</h1>';
        $html .= '<form class="cms-search" method="get" action="' . e(url('/suche')) . '">'
            . '<input type="search" name="q" value="' . e($query) . '" placeholder="Suchbegriff …">'
            . '<button type="submit" class="cms-btn cms-btn-primary">Suchen</button></form>';

        if (mb_strlen($query) >= 2) {
            $results = [];
            foreach (Page::search($query) as $page) {
                $results[] = ['url' => page_url($page), 'title' => $page['title'],
                    'snippet' => $this->snippet((string) ($page['meta_description'] ?: $page['content'] ?? ''), $query)];
            }
            foreach (\Models\Post::search($query) as $post) {
                $type = $post['type'] === 'event' ? 'events' : 'news';
                $results[] = ['url' => url('/' . $type . '/' . $post['slug']), 'title' => $post['title'],
                    'snippet' => $this->snippet((string) ($post['excerpt'] ?: $post['body'] ?? ''), $query)];
            }

            if ($results === []) {
                $html .= '<p class="cms-empty">Keine Treffer für „' . e($query) . '“.</p>';
            } else {
                $html .= '<p class="cms-search-count">' . count($results) . ' Treffer für „' . e($query) . '“:</p>';
                $html .= '<div class="cms-search-results">';
                foreach ($results as $result) {
                    $html .= '<article class="cms-search-hit"><h3><a href="' . e($result['url']) . '">' . e($result['title']) . '</a></h3>';
                    if ($result['snippet'] !== '') {
                        $html .= '<p>' . e($result['snippet']) . '</p>';
                    }
                    $html .= '</article>';
                }
                $html .= '</div>';
            }
        } elseif ($query !== '') {
            $html .= '<p class="cms-empty">Bitte mindestens 2 Zeichen eingeben.</p>';
        }
        $html .= '</div>';

        echo (new Renderer())->renderRaw('Suche', $html);
    }

    private function snippet(string $raw, string $query): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(
            // JSON-Inhalte grob in Text verwandeln
            str_replace(['\\n', '","', '":"'], ' ', $raw)
        )) ?? '');
        if ($text === '') {
            return '';
        }
        $pos = mb_stripos($text, $query);
        $start = $pos !== false ? max(0, $pos - 60) : 0;
        $snippet = mb_substr($text, $start, 180);
        return ($start > 0 ? '… ' : '') . $snippet . (mb_strlen($text) > $start + 180 ? ' …' : '');
    }

    /* ---------- Sitemap ---------- */

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        $urls = [];
        foreach (Page::all() as $page) {
            if ((int) $page['published'] === 1 && !(int) ($page['noindex'] ?? 0)) {
                $urls[] = [page_url($page), $page['updated_at'] ?? null];
            }
        }
        foreach (\Models\Post::allPublished() as $post) {
            $type = $post['type'] === 'event' ? 'events' : 'news';
            $urls[] = [url('/' . $type . '/' . $post['slug']), $post['updated_at'] ?? null];
        }

        $host = (($_SERVER['HTTPS'] ?? '') !== '' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$path, $updated]) {
            echo '  <url><loc>' . e($host . $path) . '</loc>';
            if ($updated) {
                echo '<lastmod>' . date('Y-m-d', (int) strtotime((string) $updated)) . '</lastmod>';
            }
            echo "</url>\n";
        }
        echo '</urlset>';
    }

    public function newsShow(string $slug): void
    {
        $post = \Models\Post::findPublished('news', $slug);
        if ($post === null) {
            $this->notFound();
        }
        echo (new Renderer())->renderPost($post, 'news');
    }

    public function eventShow(string $slug): void
    {
        $post = \Models\Post::findPublished('event', $slug);
        if ($post === null) {
            $this->notFound();
        }
        echo (new Renderer())->renderPost($post, 'events');
    }

    /** Absenden des Kontaktformular-Blocks (öffentlich, CSRF-geschützt). */
    public function formSubmit(): void
    {
        $pageId = (int) ($_POST['form_page'] ?? 0);
        $blockId = preg_replace('/[^a-z0-9\-]/i', '', (string) ($_POST['form_block'] ?? '')) ?: '';
        $page = $pageId > 0 ? Page::find($pageId) : null;
        if ($page === null || $blockId === '') {
            $this->notFound();
        }
        $back = '/' . $page['slug'];

        // Formular-Konfiguration aus dem gespeicherten Seiteninhalt lesen
        // (niemals aus dem Request – der ist manipulierbar).
        $config = null;
        $content = json_decode((string) ($page['content'] ?? ''), true);
        foreach (($content['rows'] ?? []) as $row) {
            foreach (($row['columns'] ?? []) as $column) {
                foreach (($column['blocks'] ?? []) as $block) {
                    if (($block['type'] ?? '') === 'form' && ($block['id'] ?? '') === $blockId) {
                        $config = (array) ($block['data'] ?? []);
                    }
                }
            }
        }
        if ($config === null) {
            $this->notFound();
        }

        // Honeypot gefüllt → Bot; Erfolg vortäuschen, nichts senden.
        if (trim($_POST['website'] ?? '') !== '') {
            redirect($back . '?sent=' . $blockId . '#f-' . $blockId);
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $needsName = !isset($config['show_name']) || !empty($config['show_name']);

        if ($message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || ($needsName && $name === '')) {
            redirect($back . '?formerror=' . $blockId . '#f-' . $blockId);
        }

        // Eigene Felder aus dem Formular-Baukasten einsammeln und validieren.
        $customValues = [];
        $customFields = is_array($config['fields'] ?? null) ? $config['fields'] : [];
        foreach ($customFields as $index => $field) {
            if (!is_array($field) || trim((string) ($field['label'] ?? '')) === '') {
                continue;
            }
            $label = trim((string) $field['label']);
            $value = trim((string) ($_POST['custom'][$index] ?? ''));
            if (($field['type'] ?? '') === 'checkbox') {
                $value = $value !== '' ? 'Ja' : 'Nein';
            }
            if (!empty($field['required']) && ($value === '' || $value === 'Nein')) {
                redirect($back . '?formerror=' . $blockId . '#f-' . $blockId);
            }
            if ($value !== '') {
                $customValues[$label] = mb_substr($value, 0, 2000);
            }
        }

        $to = trim((string) ($config['recipient'] ?? '')) ?: Setting::get('contact_email');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            redirect($back . '?formerror=' . $blockId . '#f-' . $blockId);
        }

        $subject = trim((string) ($config['subject'] ?? '')) ?: 'Neue Nachricht über das Kontaktformular';
        $lines = [];
        $entry = [];
        if ($name !== '') {
            $lines[] = 'Name: ' . $name;
            $entry['Name'] = $name;
        }
        $lines[] = 'E-Mail: ' . $email;
        $entry['E-Mail'] = $email;
        if ($phone !== '') {
            $lines[] = 'Telefon: ' . $phone;
            $entry['Telefon'] = $phone;
        }
        foreach ($customValues as $label => $value) {
            $lines[] = $label . ': ' . $value;
            $entry[$label] = $value;
        }
        $lines[] = 'Seite: ' . $page['title'];
        $lines[] = '';
        $lines[] = $message;
        $entry['Nachricht'] = $message;

        // Zusätzlich zum Mailversand im Admin unter "Formular-Einsendungen" ablegen.
        \Models\FormEntry::create((string) $page['title'], $entry);

        $error = \Core\Mailer::send($to, $subject, implode("\n", $lines), $email);
        redirect($back . ($error === null ? '?sent=' : '?formerror=') . $blockId . '#f-' . $blockId);
    }

    public function notFound(): never
    {
        http_response_code(404);
        \Core\View::render('site/404');
        exit;
    }
}
