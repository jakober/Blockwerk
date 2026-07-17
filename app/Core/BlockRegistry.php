<?php
declare(strict_types=1);

namespace Core;

/**
 * Zentrale Registrierung aller Inhalts-Block-Typen.
 * Neue Block-Typen: hier einen Render-Eintrag ergänzen und in
 * public/assets/js/editor.js einen passenden Eintrag in blockDefs anlegen.
 */
class BlockRegistry
{
    public static function types(): array
    {
        return ['heading', 'text', 'image', 'button', 'html', 'divider', 'spacer'];
    }

    public static function render(array $block): string
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        return match ($block['type'] ?? '') {
            'heading' => self::heading($data),
            'text' => '<div class="cms-text">' . (string) ($data['html'] ?? '') . '</div>',
            'image' => self::image($data),
            'button' => self::button($data),
            'html' => (string) ($data['code'] ?? ''),
            'divider' => '<hr class="cms-divider">',
            'spacer' => '<div class="cms-spacer" style="height:' . max(0, (int) ($data['height'] ?? 40)) . 'px"></div>',
            default => '',
        };
    }

    private static function heading(array $data): string
    {
        $level = in_array($data['level'] ?? 'h2', ['h1', 'h2', 'h3', 'h4'], true) ? $data['level'] : 'h2';
        return "<{$level} class=\"cms-heading\">" . e((string) ($data['text'] ?? '')) . "</{$level}>";
    }

    private static function image(array $data): string
    {
        $src = (string) ($data['src'] ?? '');
        if ($src === '') {
            return '';
        }
        return '<img class="cms-image" src="' . e($src) . '" alt="' . e((string) ($data['alt'] ?? '')) . '">';
    }

    private static function button(array $data): string
    {
        $style = ($data['style'] ?? 'primary') === 'outline' ? 'cms-btn-outline' : 'cms-btn-primary';
        return '<a class="cms-btn ' . $style . '" href="' . e((string) ($data['url'] ?? '#')) . '">'
            . e((string) ($data['text'] ?? '')) . '</a>';
    }
}
