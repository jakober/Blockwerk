<?php
/** Router für den PHP-Entwicklungsserver: php -S 127.0.0.1:8100 ai-server/router.php */
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_ends_with($path, 'admin.php')) {
    require __DIR__ . '/admin.php';
    return;
}
require __DIR__ . '/index.php';
