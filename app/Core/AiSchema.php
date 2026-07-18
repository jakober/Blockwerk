<?php
declare(strict_types=1);

namespace Core;

use Models\Layout;
use Models\Media;
use Models\Page;
use Models\Setting;

/**
 * Das „Systemwissen" des KI-Assistenten: baut den System-Prompt mit der
 * kompletten Blockwerk-Orange-Architektur (Content-JSON, Block-Katalog,
 * Gestaltungsregeln) plus dem Live-Kontext der jeweiligen Installation
 * (Seitenbaum, Layout-Farben, Mediathek) und definiert die Tools.
 */
class AiSchema
{
    public static function systemPrompt(): string
    {
        $siteName = Setting::get('site_name', 'Meine Website');
        $langs = implode(', ', cms_langs());

        $pages = array_map(
            static fn (array $p): string => '- id=' . $p['id'] . ' „' . $p['title'] . '“ (slug: ' . $p['slug'] . ', Sprache: ' . ($p['lang'] ?? 'de') . ($p['published'] ? '' : ', Entwurf') . ')',
            Page::all()
        );

        $layouts = [];
        foreach (Layout::all() as $layout) {
            $design = json_decode((string) ($layout['design'] ?? ''), true) ?: [];
            $colors = $design['colors'] ?? [];
            $layouts[] = '- id=' . $layout['id'] . ' „' . $layout['name'] . '“'
                . ($colors !== [] ? ' (Farben: ' . implode(', ', array_map(
                    static fn (string $k, string $v): string => $k . '=' . $v,
                    array_keys($colors),
                    array_values($colors)
                )) . ')' : '');
        }

        $media = [];
        foreach (array_slice(Media::all(), 0, 25) as $item) {
            if (str_starts_with((string) $item['mime'], 'image/')) {
                $media[] = '- ' . url('/' . $item['path']) . ' („' . $item['filename'] . '“)';
            }
        }

        $pagesList = $pages !== [] ? implode("\n", $pages) : '- (noch keine Seiten)';
        $layoutsList = $layouts !== [] ? implode("\n", $layouts) : '- (keine Layouts)';
        $mediaList = $media !== [] ? implode("\n", $media) : '- (noch keine Bilder – nutze generate_image)';

        return <<<PROMPT
Du bist der KI-Assistent von „Blockwerk Orange“, einem deutschen CMS. Du hilfst dem Betreiber der Website „{$siteName}“, Seiten und Inhalte zu erstellen. Du kennst die Architektur des Systems perfekt und arbeitest ausschließlich über die bereitgestellten Tools. Antworte immer auf Deutsch, freundlich und knapp.

## Inhalts-Format (Content-JSON)

Eine Seite besteht aus {"rows": [...]}. Jede Zeile (row) hat:
- "columns": Liste von Spalten. Jede Spalte: {"span": 1–12, "blocks": [...]}. Die Spannen einer Zeile sollten zusammen 12 ergeben (z. B. [12], [6,6], [4,4,4], [8,4]).
- optional "style": {"bg": Hintergrund, "width": ""|"full", "pt": px, "pb": px, "bp": px}. "bg" ist ENTWEDER ein Hex-Wert "#rrggbb" ODER ein Paletten-Schlüssel: "primary", "accent", "surface", "page" (folgt dann automatisch den Layout-Farben – bevorzuge die Palette!). "width":"full" lässt die Inhalte über die volle Browserbreite laufen. "pt"/"pb" = Innenabstand oben/unten in px (Sektionen: 40–80 wirkt gut).

Jeder Block: {"type": "...", "data": {...}}. Optional data._style = {"mt","mb","p","radius": px, "align": "left|center|right", "color","bg": "#rrggbb", "malign","mmt","mmb","mp": Mobil-Überschreibungen}.

## Block-Katalog (type → data-Felder)

- heading: text, level ("h1"–"h4"), variant ("standard","accent-line","boxed","centered")
- text: html (sauberes HTML: <p>,<strong>,<em>,<ul>,<li>,<a>), variant ("standard","infobox","note")
- image: src (URL), alt, caption, link, variant ("standard","frame","shadow","round")
- gallery: images:[{src,caption,alt}], columns (2–6), lightbox (0/1), show_captions (0/1), variant ("standard","cards","seamless")
- slider: images:[{src,caption}], height (px), autoplay, interval, arrows, dots
- hero: slides:[{src,title,text,button_text,button_url}], height (% Bildschirmhöhe, 50–75 gut), overlay ("none","light","medium","dark"), autoplay, interval, arrows, dots — Volle-Breite-Hingucker für Seitenanfänge
- button: text, url, style ("primary","accent","outline","ghost"), size ("small","normal","large")
- video: url (YouTube/Vimeo/MP4)
- quote: text, author, variant ("standard","card","big")
- accordion: items:[{title,text}], first_open, variant ("standard","cards")
- news / events: count, layout ("cards","list","minimal"), columns, show_image, show_date, show_excerpt (events zusätzlich show_location)
- form: recipient (leer = Standard), subject, button_text, success, show_name, show_phone, fields:[{label,type("text","textarea","select","checkbox"),options,required}] — Kontaktformular
- search: placeholder, button_text
- map: lat, lon, zoom, height — OpenStreetMap
- team: members:[{src,name,role,text}], columns (2–4)
- pricing: plans:[{title,price,period,features (eine Leistung pro Zeile),button_text,button_url,highlight}]
- countdown: target ("JJJJ-MM-TT HH:MM"), title, expired_text
- social: links:[{network("facebook","instagram","x","youtube","linkedin","tiktok","whatsapp","mail","phone"),url}], size
- html: code | divider: – | spacer: height (px)

## Gestaltungsregeln für moderne Seiten

1. Starte Landingpages mit einem hero-Block (Zeile [12]) mit generiertem Bild, prägnantem Titel und Button.
2. Wechsle Sektions-Hintergründe ab: normale Zeilen und Zeilen mit style {"bg":"surface","pt":50,"pb":50} – so entsteht Rhythmus. Nutze die Palette, keine harten Hex-Farben.
3. Nutze 3-Karten-Muster ([4,4,4] mit heading h3 + text variant "infobox") für Vorteile/Leistungen, [6,6] für Text+Bild im Wechsel (Bild mit variant "shadow").
4. Texte: konkret, deutsch, kein Lorem ipsum, 2–4 Sätze pro Textblock, Überschriften-Hierarchie sauber (eine h1 pro Seite).
5. Generiere für zentrale Stellen (Hero, Text+Bild) Bilder per generate_image mit detaillierten fotografischen Prompts (Stil, Licht, Motiv – ohne Text im Bild). Nutze alternativ passende vorhandene Mediathek-Bilder.
6. Schließe Kontakt-/Landingpages mit einer Kontakt-Sektion ab (heading + form).

## Arbeitsweise

- Erstelle erst Bilder (generate_image), dann die Seite (create_page) mit den gelieferten Bild-URLs.
- Für Änderungswünsche: get_page lesen, dann update_page mit dem vollständigen neuen Content-JSON.
- Wenn ein Tool "FEHLER:" meldet, korrigiere die Eingabe und versuche es erneut.
- Fasse am Ende kurz zusammen, was du angelegt hast.

## Diese Installation

Sprachen: {$langs}
Vorhandene Seiten:
{$pagesList}

Layouts:
{$layoutsList}

Vorhandene Mediathek-Bilder (direkt nutzbar):
{$mediaList}
PROMPT
        ;
    }

    /** Tools im Anthropic-Format. */
    public static function tools(): array
    {
        $contentSchema = [
            'type' => 'object',
            'description' => 'Content-JSON der Seite ({"rows": [...]}) gemäß System-Prompt.',
            'properties' => ['rows' => ['type' => 'array']],
            'required' => ['rows'],
        ];

        return [
            [
                'name' => 'create_page',
                'description' => 'Legt eine neue Seite im CMS an (veröffentlicht) und liefert ihre URL zurück.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Seitentitel'],
                        'slug' => ['type' => 'string', 'description' => 'URL-Slug (optional, sonst aus dem Titel)'],
                        'parent_id' => ['type' => 'integer', 'description' => 'ID der Elternseite (optional)'],
                        'in_menu' => ['type' => 'integer', 'description' => '1 = im Hauptmenü anzeigen, 0 = nicht'],
                        'meta_description' => ['type' => 'string', 'description' => 'SEO-Beschreibung (optional)'],
                        'content' => $contentSchema,
                    ],
                    'required' => ['title', 'content'],
                ],
            ],
            [
                'name' => 'update_page',
                'description' => 'Ersetzt den Inhalt einer bestehenden Seite durch neues Content-JSON (alter Stand wird als Version gesichert).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'page_id' => ['type' => 'integer'],
                        'content' => $contentSchema,
                    ],
                    'required' => ['page_id', 'content'],
                ],
            ],
            [
                'name' => 'get_page',
                'description' => 'Liest Titel und Content-JSON einer Seite (vor Änderungen aufrufen).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['page_id' => ['type' => 'integer']],
                    'required' => ['page_id'],
                ],
            ],
            [
                'name' => 'generate_image',
                'description' => 'Generiert ein Bild per KI, speichert es in der Mediathek und liefert die Bild-URL. Kostet zusätzliches Token-Guthaben.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'prompt' => ['type' => 'string', 'description' => 'Detaillierte Bildbeschreibung (fotografisch, ohne Text im Bild)'],
                        'filename' => ['type' => 'string', 'description' => 'Sprechender Dateiname ohne Endung, z. B. "cafe-innenraum"'],
                    ],
                    'required' => ['prompt', 'filename'],
                ],
            ],
        ];
    }
}
