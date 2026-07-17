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

    public static function fetch(string $template, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include APP_PATH . '/Views/' . $template . '.php';
        return (string) ob_get_clean();
    }
}
