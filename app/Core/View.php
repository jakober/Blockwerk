<?php
declare(strict_types=1);

namespace Core;

class View
{
    public static function render(string $template, array $data = [], ?string $shell = null): void
    {
        $content = self::fetch($template, $data);
        if ($shell !== null) {
            echo self::fetch($shell, $data + ['content' => $content]);
        } else {
            echo $content;
        }
    }

    /**
     * Interne Variablen sind mit __ geprefixt, damit extract() sie nicht
     * mit View-Daten gleichen Namens verwechselt (z. B. dem Schlüssel
     * "template" im Template-Formular).
     */
    public static function fetch(string $__template, array $__data = []): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        include APP_PATH . '/Views/' . $__template . '.php';
        return (string) ob_get_clean();
    }
}
