<?php
declare(strict_types=1);

namespace Core;

use Controllers\Admin\DashboardController;
use Controllers\Admin\EventsController;
use Controllers\Admin\FontController;
use Controllers\Admin\LayoutController;
use Controllers\Admin\MediaController;
use Controllers\Admin\NewsController;
use Controllers\Admin\PageController;
use Controllers\Admin\SettingsController;
use Controllers\Admin\TemplateController;
use Controllers\AuthController;
use Controllers\InstallController;
use Controllers\SiteController;

class App
{
    private static string $base = '';

    public static function base(): string
    {
        return self::$base;
    }

    public function run(): void
    {
        // HTML nie im Browser heuristisch cachen – sonst sehen z. B. Handys
        // Menü- oder Design-Änderungen erst verspätet. Statische Assets
        // liefert der Webserver direkt aus, die cachen weiterhin normal.
        if (!headers_sent()) {
            header('Cache-Control: no-cache, must-revalidate');
        }

        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $this->resolveBase($uriPath);

        $path = $uriPath;
        if (self::$base !== '' && str_starts_with($path, self::$base)) {
            $path = substr($path, strlen(self::$base));
        }
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $installed = is_file(CONFIG_FILE);
        if (!$installed && !str_starts_with($path, '/install')) {
            redirect('/install');
        }
        if ($installed && str_starts_with($path, '/install')) {
            redirect('/');
        }

        if ($installed) {
            $this->ensureSchema();
        }

        if ($method === 'POST') {
            csrf_check();
            // Inhaltsänderungen im Admin leeren den Seiten-Cache.
            if ($installed && str_starts_with($path, '/admin')) {
                Cache::clear();
            }
        }

        $router = new Router();
        $this->registerRoutes($router, $installed);

        // Seiten-Cache: nur anonyme GET-Anfragen ohne Query-String.
        $cacheable = $installed && $method === 'GET'
            && !str_starts_with($path, '/admin') && $path !== '/login'
            && empty($_SESSION['user_id'])
            && ($_SERVER['QUERY_STRING'] ?? '') === ''
            && Cache::enabled();

        if ($cacheable) {
            $cached = Cache::get($path);
            if ($cached !== null) {
                echo $cached;
                return;
            }
            ob_start();
            $router->dispatch($method, $path);
            $html = (string) ob_get_clean();
            // Seiten mit Formularen (Session-CSRF-Token) nie cachen.
            if (http_response_code() === 200 && !str_contains($html, 'name="_csrf"')) {
                Cache::put($path, $html);
            }
            echo $html;
            return;
        }

        $router->dispatch($method, $path);
    }

    /**
     * Selbstheilende Migration: Passt das Datenbankschema automatisch an,
     * sobald die Code-Version wechselt. Wichtig, weil der Updater während
     * des Update-Requests noch mit den ALTEN Klassen läuft und dessen
     * Schema-Update deshalb einen Versionsstand hinterherhinken kann.
     */
    private function ensureSchema(): void
    {
        try {
            $codeVersion = Updater::currentVersion();
            if (\Models\Setting::get('schema_version', '') !== $codeVersion) {
                Database::createSchema(Database::pdo());
                MenuDesign::migrate();
                \Models\Setting::set('schema_version', $codeVersion);
                Cache::clear();
            }
        } catch (\Throwable) {
            // Bewusst still: Ist die Datenbank nicht erreichbar, meldet
            // sich der eigentliche Seitenaufruf mit einer klaren Meldung.
        }
    }

    private function resolveBase(string $uriPath): void
    {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        // Bei Rewrite aus dem Projekt-Root nach public/ taucht /public nicht in der URL auf.
        if ($base !== '' && !str_starts_with($uriPath, $base) && str_ends_with($base, '/public')) {
            $base = substr($base, 0, -strlen('/public'));
        }
        self::$base = $base;
    }

    private function registerRoutes(Router $router, bool $installed = true): void
    {
        // Installation
        $router->add('GET', '/install', [InstallController::class, 'index']);
        $router->add('POST', '/install/database', [InstallController::class, 'database']);
        $router->add('GET', '/install/site', [InstallController::class, 'site']);
        $router->add('POST', '/install/finish', [InstallController::class, 'finish']);

        // Authentifizierung
        $router->add('GET', '/login', [AuthController::class, 'showLogin']);
        $router->add('POST', '/login', [AuthController::class, 'login']);
        $router->add('POST', '/logout', [AuthController::class, 'logout']);

        // Admin
        $router->add('GET', '/admin', [DashboardController::class, 'index']);

        $router->add('GET', '/admin/pages', [PageController::class, 'index']);
        $router->add('GET', '/admin/pages/new', [PageController::class, 'create']);
        $router->add('POST', '/admin/pages', [PageController::class, 'store']);
        $router->add('POST', '/admin/pages/reorder', [PageController::class, 'reorder']);
        $router->add('GET', '/admin/pages/{id}/edit', [PageController::class, 'edit']);
        $router->add('POST', '/admin/pages/{id}', [PageController::class, 'update']);
        $router->add('POST', '/admin/pages/{id}/delete', [PageController::class, 'delete']);
        $router->add('GET', '/admin/pages/{id}/editor', [PageController::class, 'editor']);
        $router->add('POST', '/admin/pages/{id}/content', [PageController::class, 'saveContent']);
        $router->add('GET', '/admin/pages/trash', [PageController::class, 'trash']);
        $router->add('POST', '/admin/pages/{id}/restore', [PageController::class, 'restore']);
        $router->add('POST', '/admin/pages/{id}/destroy', [PageController::class, 'destroy']);
        $router->add('POST', '/admin/pages/{id}/duplicate', [PageController::class, 'duplicate']);
        $router->add('GET', '/admin/pages/{id}/versions', [PageController::class, 'versions']);
        $router->add('POST', '/admin/pages/{id}/versions/{vid}/restore', [PageController::class, 'restoreVersion']);

        $router->add('GET', '/admin/forms', [\Controllers\Admin\FormEntriesController::class, 'index']);
        $router->add('POST', '/admin/forms/{id}/delete', [\Controllers\Admin\FormEntriesController::class, 'delete']);

        $router->add('GET', '/admin/globals', [\Controllers\Admin\GlobalBlockController::class, 'index']);
        $router->add('POST', '/admin/globals', [\Controllers\Admin\GlobalBlockController::class, 'store']);

        $router->add('POST', '/admin/backup', [\Controllers\Admin\BackupController::class, 'download']);
        $router->add('POST', '/admin/restore/chunk', [\Controllers\Admin\BackupController::class, 'restoreChunk']);
        $router->add('POST', '/admin/restore/run', [\Controllers\Admin\BackupController::class, 'restoreRun']);

        $router->add('GET', '/admin/themes', [\Controllers\Admin\ThemeController::class, 'index']);
        $router->add('POST', '/admin/themes/{key}/apply', [\Controllers\Admin\ThemeController::class, 'apply']);
        $router->add('POST', '/admin/themes/{key}/delete', [\Controllers\Admin\ThemeController::class, 'delete']);

        $router->add('GET', '/admin/ai', [\Controllers\Admin\AiController::class, 'index']);
        $router->add('POST', '/admin/ai/chat', [\Controllers\Admin\AiController::class, 'chat']);
        $router->add('POST', '/admin/ai/clear', [\Controllers\Admin\AiController::class, 'clear']);
        $router->add('GET', '/admin/ai-admin', [\Controllers\Admin\AiAdminController::class, 'index']);
        $router->add('POST', '/admin/ai-admin/config', [\Controllers\Admin\AiAdminController::class, 'saveConfig']);
        $router->add('POST', '/admin/ai-admin/license', [\Controllers\Admin\AiAdminController::class, 'license']);

        $router->add('GET', '/admin/menu', [\Controllers\Admin\MenuController::class, 'edit']);
        $router->add('POST', '/admin/menu', [\Controllers\Admin\MenuController::class, 'save']);
        $router->add('POST', '/admin/menu/reset', [\Controllers\Admin\MenuController::class, 'reset']);

        $router->add('GET', '/admin/layouts', [LayoutController::class, 'index']);
        $router->add('POST', '/admin/layouts/visual-new', [LayoutController::class, 'visualNew']);
        $router->add('GET', '/admin/layouts/{id}/builder', [LayoutController::class, 'builder']);
        $router->add('POST', '/admin/layouts/{id}/builder-content', [LayoutController::class, 'saveBuilder']);
        $router->add('POST', '/admin/layouts/{id}/builder-reset', [LayoutController::class, 'builderReset']);
        $router->add('GET', '/admin/layouts/new', [LayoutController::class, 'create']);
        $router->add('POST', '/admin/layouts', [LayoutController::class, 'store']);
        $router->add('GET', '/admin/layouts/{id}/edit', [LayoutController::class, 'edit']);
        $router->add('POST', '/admin/layouts/{id}', [LayoutController::class, 'update']);
        $router->add('POST', '/admin/layouts/{id}/delete', [LayoutController::class, 'delete']);
        $router->add('POST', '/admin/layouts/{id}/make-default', [LayoutController::class, 'makeDefault']);

        $router->add('GET', '/admin/templates', [TemplateController::class, 'index']);
        $router->add('GET', '/admin/templates/new', [TemplateController::class, 'create']);
        $router->add('POST', '/admin/templates', [TemplateController::class, 'store']);
        $router->add('GET', '/admin/templates/{id}/edit', [TemplateController::class, 'edit']);
        $router->add('POST', '/admin/templates/{id}', [TemplateController::class, 'update']);
        $router->add('POST', '/admin/templates/{id}/delete', [TemplateController::class, 'delete']);

        $router->add('GET', '/admin/media', [MediaController::class, 'index']);
        $router->add('GET', '/admin/media/list', [MediaController::class, 'list']);
        $router->add('POST', '/admin/media/folders', [MediaController::class, 'folderCreate']);
        $router->add('POST', '/admin/media/folders/{id}', [MediaController::class, 'folderRename']);
        $router->add('POST', '/admin/media/folders/{id}/delete', [MediaController::class, 'folderDelete']);
        $router->add('POST', '/admin/media/{id}/update', [MediaController::class, 'updateMeta']);
        $router->add('POST', '/admin/media/upload', [MediaController::class, 'upload']);
        $router->add('POST', '/admin/media/{id}/delete', [MediaController::class, 'delete']);

        foreach (['news' => NewsController::class, 'events' => EventsController::class] as $prefix => $controller) {
            $router->add('GET', "/admin/$prefix", [$controller, 'index']);
            $router->add('GET', "/admin/$prefix/new", [$controller, 'create']);
            $router->add('POST', "/admin/$prefix", [$controller, 'store']);
            $router->add('GET', "/admin/$prefix/{id}/edit", [$controller, 'edit']);
            $router->add('POST', "/admin/$prefix/{id}", [$controller, 'update']);
            $router->add('POST', "/admin/$prefix/{id}/delete", [$controller, 'delete']);
        }

        $router->add('GET', '/admin/users', [\Controllers\Admin\UserController::class, 'index']);
        $router->add('GET', '/admin/users/new', [\Controllers\Admin\UserController::class, 'create']);
        $router->add('POST', '/admin/users', [\Controllers\Admin\UserController::class, 'store']);
        $router->add('GET', '/admin/users/{id}/edit', [\Controllers\Admin\UserController::class, 'edit']);
        $router->add('POST', '/admin/users/{id}', [\Controllers\Admin\UserController::class, 'update']);
        $router->add('POST', '/admin/users/{id}/delete', [\Controllers\Admin\UserController::class, 'delete']);

        $router->add('GET', '/admin/fonts', [FontController::class, 'index']);
        $router->add('POST', '/admin/fonts', [FontController::class, 'store']);
        $router->add('POST', '/admin/fonts/{id}/delete', [FontController::class, 'delete']);

        $router->add('POST', '/admin/preview/blocks', [\Controllers\Admin\PreviewController::class, 'blocks']);

        $router->add('GET', '/admin/update', [\Controllers\Admin\UpdateController::class, 'index']);
        $router->add('POST', '/admin/update/check', [\Controllers\Admin\UpdateController::class, 'check']);
        $router->add('POST', '/admin/update/run', [\Controllers\Admin\UpdateController::class, 'run']);

        $router->add('GET', '/admin/settings', [SettingsController::class, 'index']);
        $router->add('POST', '/admin/settings', [SettingsController::class, 'save']);

        // Shop-Verwaltung
        $router->add('GET', '/admin/shop/products', [\Controllers\Admin\ShopProductController::class, 'index']);
        $router->add('GET', '/admin/shop/products/new', [\Controllers\Admin\ShopProductController::class, 'create']);
        $router->add('POST', '/admin/shop/products', [\Controllers\Admin\ShopProductController::class, 'store']);
        $router->add('GET', '/admin/shop/products/{id}/edit', [\Controllers\Admin\ShopProductController::class, 'edit']);
        $router->add('POST', '/admin/shop/products/{id}', [\Controllers\Admin\ShopProductController::class, 'update']);
        $router->add('POST', '/admin/shop/products/{id}/delete', [\Controllers\Admin\ShopProductController::class, 'delete']);

        $router->add('GET', '/admin/shop/categories', [\Controllers\Admin\ShopCategoryController::class, 'index']);
        $router->add('GET', '/admin/shop/categories/new', [\Controllers\Admin\ShopCategoryController::class, 'create']);
        $router->add('POST', '/admin/shop/categories', [\Controllers\Admin\ShopCategoryController::class, 'store']);
        $router->add('GET', '/admin/shop/categories/{id}/edit', [\Controllers\Admin\ShopCategoryController::class, 'edit']);
        $router->add('POST', '/admin/shop/categories/{id}', [\Controllers\Admin\ShopCategoryController::class, 'update']);
        $router->add('POST', '/admin/shop/categories/{id}/delete', [\Controllers\Admin\ShopCategoryController::class, 'delete']);

        $router->add('GET', '/admin/shop/orders', [\Controllers\Admin\ShopOrderController::class, 'index']);
        $router->add('GET', '/admin/shop/orders/{id}', [\Controllers\Admin\ShopOrderController::class, 'show']);
        $router->add('POST', '/admin/shop/orders/{id}/status', [\Controllers\Admin\ShopOrderController::class, 'setStatus']);
        $router->add('POST', '/admin/shop/orders/{id}/delete', [\Controllers\Admin\ShopOrderController::class, 'delete']);

        $router->add('GET', '/admin/shop/settings', [\Controllers\Admin\ShopSettingsController::class, 'index']);
        $router->add('POST', '/admin/shop/settings', [\Controllers\Admin\ShopSettingsController::class, 'save']);
        $router->add('POST', '/admin/shop/shipping', [\Controllers\Admin\ShopSettingsController::class, 'shippingStore']);
        $router->add('POST', '/admin/shop/shipping/{id}', [\Controllers\Admin\ShopSettingsController::class, 'shippingUpdate']);
        $router->add('POST', '/admin/shop/shipping/{id}/delete', [\Controllers\Admin\ShopSettingsController::class, 'shippingDelete']);

        // Shop-Frontend – nur wenn aktiviert; Basis ist der Slug der Shop-Hauptseite.
        if ($installed && \Core\Shop::enabled()) {
            $b = '/' . trim(\Core\Shop::rootSlug(), '/');
            $router->add('GET', $b, [\Controllers\ShopController::class, 'index']);
            $router->add('GET', $b . '/warenkorb', [\Controllers\ShopController::class, 'cart']);
            $router->add('POST', $b . '/warenkorb/add', [\Controllers\ShopController::class, 'cartAdd']);
            $router->add('POST', $b . '/warenkorb/update', [\Controllers\ShopController::class, 'cartUpdate']);
            $router->add('POST', $b . '/warenkorb/remove', [\Controllers\ShopController::class, 'cartRemove']);
            $router->add('GET', $b . '/kasse', [\Controllers\ShopController::class, 'checkout']);
            $router->add('POST', $b . '/kasse', [\Controllers\ShopController::class, 'placeOrder']);
            $router->add('POST', $b . '/paypal/create', [\Controllers\ShopController::class, 'paypalCreate']);
            $router->add('POST', $b . '/paypal/capture', [\Controllers\ShopController::class, 'paypalCapture']);
            $router->add('GET', $b . '/bestellung/{token}', [\Controllers\ShopController::class, 'orderConfirm']);
            $router->add('GET', $b . '/produkt/{slug}', [\Controllers\ShopController::class, 'product']);
            $router->add('GET', $b . '/kategorie/{slug}', [\Controllers\ShopController::class, 'category']);
        }

        // Öffentliche Seiten (Catch-all zuletzt)
        $router->add('POST', '/form/submit', [SiteController::class, 'formSubmit']);
        $router->add('GET', '/sitemap.xml', [SiteController::class, 'sitemap']);
        $router->add('GET', '/suche', [SiteController::class, 'search']);
        $router->add('GET', '/news/{slug}', [SiteController::class, 'newsShow']);
        $router->add('GET', '/events/{slug}', [SiteController::class, 'eventShow']);

        // Sprach-Präfixe für alle Nicht-Standardsprachen (z. B. /en, /en/about)
        if ($installed) {
            foreach (array_slice(cms_langs(), 1) as $lang) {
                $router->add('GET', '/' . $lang, [SiteController::class, 'homeLang'], [$lang]);
                $router->add('GET', '/' . $lang . '/{slug}', [SiteController::class, 'showLang'], [$lang]);
            }
        }

        $router->add('GET', '/', [SiteController::class, 'home']);
        $router->add('GET', '/{slug}', [SiteController::class, 'show']);
    }
}
