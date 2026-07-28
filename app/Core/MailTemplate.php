<?php
declare(strict_types=1);

namespace Core;

use Models\Setting;

/**
 * Einheitliches Aussehen für alle E-Mails an Kunden: Kopfleiste mit der
 * Blockwerk-Marke, klarer Textbereich, auffälliger Aktions-Knopf und eine
 * ruhige Fußzeile. Bewusst reines Inline-CSS mit Tabellen – nur so stellen
 * Outlook, Gmail & Co. eine Mail zuverlässig dar.
 *
 * Jede Mail geht als Text **und** HTML raus (multipart/alternative), damit sie
 * auch in Textprogrammen lesbar bleibt.
 */
class MailTemplate
{
    private const ACCENT = '#ea580c';
    private const INK = '#0f172a';
    private const MUTED = '#64748b';

    /**
     * @param string $title Überschrift der Mail
     * @param array  $blocks Absätze: string = Absatz, ['label'=>..,'value'=>..] = Datenzeile
     * @param array  $cta ['text' => 'Zum Konto', 'url' => 'https://…'] (optional)
     * @param string $footNote kleiner Hinweis unter dem Knopf (optional)
     */
    public static function html(string $title, array $blocks, array $cta = [], string $footNote = ''): string
    {
        $brand = self::brand();
        $accent = self::ACCENT;
        $ink = self::INK;
        $muted = self::MUTED;

        $content = '';
        foreach ($blocks as $block) {
            if (is_array($block)) {
                $content .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;'
                    . 'margin:0 0 6px"><tr>'
                    . '<td style="padding:7px 0;color:' . $muted . ';font-size:14px;width:150px;vertical-align:top">'
                    . e((string) ($block['label'] ?? '')) . '</td>'
                    . '<td style="padding:7px 0;color:' . $ink . ';font-size:14px;font-weight:600">'
                    . ($block['raw'] ?? e((string) ($block['value'] ?? ''))) . '</td></tr></table>';
                continue;
            }
            $content .= '<p style="margin:0 0 14px;color:' . $ink . ';font-size:15px;line-height:1.6">'
                . nl2br(e((string) $block)) . '</p>';
        }

        $button = '';
        if (!empty($cta['url']) && !empty($cta['text'])) {
            $button = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0 6px">'
                . '<tr><td style="background:' . $accent . ';border-radius:10px">'
                . '<a href="' . e((string) $cta['url']) . '" style="display:inline-block;padding:13px 26px;color:#ffffff;'
                . 'font-size:15px;font-weight:700;text-decoration:none;font-family:Arial,Helvetica,sans-serif">'
                . e((string) $cta['text']) . '</a></td></tr></table>'
                // Manche Programme unterdrücken Knöpfe – die Adresse deshalb ausschreiben.
                . '<p style="margin:6px 0 0;color:' . $muted . ';font-size:12.5px;word-break:break-all">'
                . 'Falls der Knopf nicht funktioniert: <a href="' . e((string) $cta['url']) . '" style="color:'
                . $accent . '">' . e((string) $cta['url']) . '</a></p>';
        }

        $note = $footNote !== ''
            ? '<p style="margin:18px 0 0;color:' . $muted . ';font-size:13px;line-height:1.55">' . nl2br(e($footNote)) . '</p>'
            : '';

        return '<!doctype html><html lang="de"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f4f5f7">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#f4f5f7">'
            . '<tr><td align="center" style="padding:28px 14px">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:560px;'
            . 'background:#ffffff;border-radius:14px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;'
            . 'box-shadow:0 4px 18px rgba(15,23,42,.06)">'
            // Kopf
            . '<tr><td style="padding:22px 28px 18px;border-bottom:1px solid #e5e7eb">'
            . self::logo() . '</td></tr>'
            // Inhalt
            . '<tr><td style="padding:26px 28px 28px">'
            . '<h1 style="margin:0 0 16px;font-size:21px;line-height:1.3;color:' . $ink . '">' . e($title) . '</h1>'
            . $content . $button . $note
            . '</td></tr>'
            // Fuß
            . '<tr><td style="padding:16px 28px;background:#0f172a;color:#94a3b8;font-size:12px">'
            . e($brand) . ' · Diese Nachricht wurde automatisch erzeugt.'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /** Marke als Text-Logo (die vier Kacheln als kleine Tabelle, ohne Bilddatei). */
    private static function logo(): string
    {
        $tile = static fn (string $color): string =>
            '<td style="width:11px;height:11px;background:' . $color . ';border-radius:3px"></td>';
        $gap = '<td style="width:3px"></td>';
        $mark = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="display:inline-block;vertical-align:middle">'
            . '<tr>' . $tile('#0f172a') . $gap . $tile(self::ACCENT) . '</tr>'
            . '<tr><td colspan="3" style="height:3px"></td></tr>'
            . '<tr>' . $tile('#0f172a') . $gap . $tile('#0f172a') . '</tr></table>';

        $name = self::brand();
        // Eigener Firmenname? Dann diesen zeigen, sonst die Wortmarke.
        if (strcasecmp($name, 'Blockwerk Orange') !== 0) {
            return $mark . '<span style="display:inline-block;vertical-align:middle;margin-left:9px;font-size:19px;'
                . 'font-weight:bold;color:' . self::INK . '">' . e($name) . '</span>';
        }
        return $mark . '<span style="display:inline-block;vertical-align:middle;margin-left:9px;font-size:19px;'
            . 'font-weight:bold;color:' . self::INK . '">Blockwerk<span style="color:' . self::ACCENT . '">Orange</span></span>';
    }

    private static function brand(): string
    {
        try {
            return (string) (Setting::get('site_name', '') ?: 'Blockwerk Orange');
        } catch (\Throwable) {
            return 'Blockwerk Orange';
        }
    }

    /** Aus denselben Bausteinen die Nur-Text-Fassung bauen. */
    public static function text(string $title, array $blocks, array $cta = [], string $footNote = ''): string
    {
        $lines = [$title, str_repeat('=', min(60, mb_strlen($title))), ''];
        foreach ($blocks as $block) {
            if (is_array($block)) {
                $lines[] = ($block['label'] ?? '') . ': ' . ($block['value'] ?? strip_tags((string) ($block['raw'] ?? '')));
                continue;
            }
            $lines[] = (string) $block;
            $lines[] = '';
        }
        if (!empty($cta['url'])) {
            $lines[] = '';
            $lines[] = ($cta['text'] ?? 'Hier entlang') . ': ' . $cta['url'];
        }
        if ($footNote !== '') {
            $lines[] = '';
            $lines[] = $footNote;
        }
        return implode("\n", $lines);
    }
}
