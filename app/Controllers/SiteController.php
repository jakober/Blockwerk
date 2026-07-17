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
            $this->notFound();
        }
        echo (new Renderer())->renderPage($page);
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

        $to = trim((string) ($config['recipient'] ?? '')) ?: Setting::get('contact_email');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            redirect($back . '?formerror=' . $blockId . '#f-' . $blockId);
        }

        $subject = trim((string) ($config['subject'] ?? '')) ?: 'Neue Nachricht über das Kontaktformular';
        $lines = [];
        if ($name !== '') {
            $lines[] = 'Name: ' . $name;
        }
        $lines[] = 'E-Mail: ' . $email;
        if ($phone !== '') {
            $lines[] = 'Telefon: ' . $phone;
        }
        $lines[] = 'Seite: ' . $page['title'];
        $lines[] = '';
        $lines[] = $message;

        $host = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
        $host = preg_replace('/[^a-z0-9.\-]/i', '', $host) ?: 'localhost';
        $headers = 'From: ' . Setting::get('site_name', 'Website') . ' <noreply@' . $host . '>' . "\r\n"
            . 'Reply-To: ' . str_replace(["\r", "\n"], '', $email) . "\r\n"
            . 'Content-Type: text/plain; charset=UTF-8';

        $ok = @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), implode("\n", $lines), $headers);
        redirect($back . ($ok ? '?sent=' : '?formerror=') . $blockId . '#f-' . $blockId);
    }

    public function notFound(): never
    {
        http_response_code(404);
        \Core\View::render('site/404');
        exit;
    }
}
