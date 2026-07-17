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

    public function notFound(): never
    {
        http_response_code(404);
        \Core\View::render('site/404');
        exit;
    }
}
