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
            $visual = trim((string) ($layout['builder'] ?? '')) !== '';
            $layouts[] = '- id=' . $layout['id'] . ' „' . $layout['name'] . '“ [' . ($visual ? 'visuell – per update_layout änderbar' : 'klassisch/HTML – nur lesbar') . ']'
                . ($colors !== [] ? ' (Farben: ' . implode(', ', array_map(
                    static fn (string $k, string $v): string => $k . '=' . $v,
                    array_keys($colors),
                    array_values($colors)
                )) . ')' : '');
        }

        $folderNames = [];
        foreach (\Models\MediaFolder::all() as $folder) {
            $folderNames[(int) $folder['id']] = $folder['name'];
        }
        $media = [];
        foreach (array_slice(Media::all(), 0, 30) as $item) {
            if (str_starts_with((string) $item['mime'], 'image/')) {
                $folderName = $folderNames[(int) ($item['folder_id'] ?? 0)] ?? '';
                $media[] = '- ' . url('/' . $item['path']) . ' („' . $item['filename'] . '“'
                    . ($folderName !== '' ? ', Ordner: ' . $folderName : '')
                    . (!empty($item['alt']) ? ', Alt: „' . $item['alt'] . '“' : '') . ')';
            }
        }
        if ($folderNames !== []) {
            $media[] = 'Ordner in der Mediathek: ' . implode(', ', $folderNames) . ' – mit list_media kannst du gezielt darin suchen.';
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

Jeder Block: {"type": "...", "data": {...}}. Optional data._style = {"mt","mb","p","radius": px, "align": "left|center|right", "color","bg": "#rrggbb", "anim": Scroll-Animation ("fade","up","left","right","zoom"), "malign","mmt","mmb","mp": Mobil-Überschreibungen}. "align":"center" zentriert auch Formulare und Buttons.

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
5. Generiere für zentrale Stellen (Hero, Text+Bild) Bilder per generate_image mit detaillierten fotografischen Prompts (Stil, Licht, Motiv – ohne Text im Bild). PRÜFE aber zuerst mit list_media, ob passende Bilder in der Mediathek liegen (spart Guthaben) – vor allem, wenn der Nutzer einen bestimmten Ordner nennt („nimm die Bilder aus Ordner X"), nutze GENAU diese Bilder.
6. Schließe Kontakt-/Landingpages mit einer Kontakt-Sektion ab (heading + form, gerne mit _style.align "center").
7. Setze dezente Scroll-Animationen ein (_style.anim): "up" oder "fade" für Sektions-Inhalte, z. B. bei Karten-Reihen die drei Karten mit "up". Nicht auf dem Hero, nicht bei jedem Block – sparsam wirkt hochwertig. Berücksichtige das auch bei Änderungen an bestehenden Seiten.

## Layouts (Kopf-/Fußzeile, gilt auf ALLEN Seiten)

Visuell gebaute Layouts haben ein Builder-JSON mit exakt derselben Struktur wie Seiten-Content ({"rows":[...]}), zusätzlich mit Layout-Blöcken:
- l-brand: {logo, show_name} – Logo & Website-Name
- l-menu: Hauptmenü (Daten unverändert lassen – wird über den Menü-Designer gepflegt)
- l-content: {} – Platzhalter für den Seiteninhalt, MUSS genau EINMAL vorkommen
- l-languages: {} – Sprachumschalter

Regeln für Layout-Änderungen ("überall auf der Website"):
1. IMMER zuerst get_layout aufrufen und das vorhandene Builder-JSON als Basis nehmen – Kopfzeile (l-brand/l-menu) und l-content nie entfernen oder verdoppeln.
2. Der Footer sind die Zeilen NACH der l-content-Zeile. "Über dem Footer auf allen Seiten" = neue Zeile direkt vor der ersten Footer-Zeile (nach l-content) einfügen.
3. update_layout mit dem VOLLSTÄNDIGEN neuen Builder-JSON aufrufen. Änderungen wirken sofort auf allen Seiten mit diesem Layout.
4. Klassische Layouts (HTML) kannst du nur lesen – bitte den Nutzer in dem Fall, den visuellen Baukasten zu nutzen.

## Weitere Bereiche, die du verwalten kannst

- **News & Events** (create_post/update_post/list_posts): eigene Beiträge mit Titel, Kurzbeschreibung (excerpt), Inhalt (body = sauberes HTML), Beitragsbild und – bei Events – Beginn/Ende (Format „JJJJ-MM-TT HH:MM") und Ort. type ist "news" oder "event".
- **Globale Blöcke** (create_global_block/update_global_block/list_global_blocks): wiederverwendbare Inhaltsbereiche mit demselben Content-JSON wie Seiten. Werden über den „Globaler Block"-Block auf mehreren Seiten eingebettet – eine Änderung wirkt überall.
- **Templates** (create_template/update_template/list_templates): wiederverwendbare HTML-Bausteine mit Schlüssel (für {{template:schlüssel}}). Das Menü-Template „main-menu" NICHT hier ändern – dafür ist der Menü-Designer zuständig.
- **Schriften** (load_font): lädt eine Google-Schrift herunter und speichert sie lokal (DSGVO). Danach kann sie im Layout als Überschriften-/Textschrift gewählt werden (weise den Nutzer darauf hin, dass er sie im Layout zuweisen muss).

## Arbeitsweise

- Erstelle erst Bilder (generate_image), dann die Seite (create_page) mit den gelieferten Bild-URLs.
- Für Änderungswünsche: get_page bzw. get_layout lesen, dann update_page/update_layout mit dem vollständigen neuen JSON.
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
                'name' => 'create_post',
                'description' => 'Legt einen News-Beitrag oder ein Event an (veröffentlicht).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => ['news', 'event'], 'description' => '"news" oder "event"'],
                        'title' => ['type' => 'string'],
                        'excerpt' => ['type' => 'string', 'description' => 'Kurzbeschreibung für Listen (optional)'],
                        'body' => ['type' => 'string', 'description' => 'Inhalt als HTML (<p>, <strong>, <ul>…)'],
                        'image' => ['type' => 'string', 'description' => 'Beitragsbild-URL (optional)'],
                        'start_at' => ['type' => 'string', 'description' => 'Event-Beginn „JJJJ-MM-TT HH:MM" (nur Events)'],
                        'end_at' => ['type' => 'string', 'description' => 'Event-Ende (optional)'],
                        'location' => ['type' => 'string', 'description' => 'Ort (nur Events)'],
                    ],
                    'required' => ['type', 'title'],
                ],
            ],
            [
                'name' => 'update_post',
                'description' => 'Ändert einen bestehenden News-/Event-Beitrag. Nur übergebene Felder werden geändert.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'post_id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'excerpt' => ['type' => 'string'],
                        'body' => ['type' => 'string'],
                        'image' => ['type' => 'string'],
                        'start_at' => ['type' => 'string'],
                        'end_at' => ['type' => 'string'],
                        'location' => ['type' => 'string'],
                    ],
                    'required' => ['post_id'],
                ],
            ],
            [
                'name' => 'list_posts',
                'description' => 'Listet News oder Events (id, Titel, Datum).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['type' => ['type' => 'string', 'enum' => ['news', 'event']]],
                    'required' => ['type'],
                ],
            ],
            [
                'name' => 'list_global_blocks',
                'description' => 'Listet die globalen Blöcke (id, Name).',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'create_global_block',
                'description' => 'Legt einen globalen Block (wiederverwendbarer Inhaltsbereich) mit Content-JSON an.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'content' => ['type' => 'object', 'properties' => ['rows' => ['type' => 'array']], 'required' => ['rows']],
                    ],
                    'required' => ['title', 'content'],
                ],
            ],
            [
                'name' => 'update_global_block',
                'description' => 'Ersetzt den Inhalt eines globalen Blocks (per id aus list_global_blocks).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'block_id' => ['type' => 'integer'],
                        'content' => ['type' => 'object', 'properties' => ['rows' => ['type' => 'array']], 'required' => ['rows']],
                    ],
                    'required' => ['block_id', 'content'],
                ],
            ],
            [
                'name' => 'list_templates',
                'description' => 'Listet die Templates (id, Name, Schlüssel).',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'create_template',
                'description' => 'Legt ein Template (wiederverwendbarer HTML-Baustein) an.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'key' => ['type' => 'string', 'description' => 'Schlüssel für {{template:schlüssel}}'],
                        'html' => ['type' => 'string'],
                    ],
                    'required' => ['name', 'key', 'html'],
                ],
            ],
            [
                'name' => 'update_template',
                'description' => 'Ändert ein Template (per id). Nicht für das Menü-Template „main-menu" verwenden.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'html' => ['type' => 'string'],
                    ],
                    'required' => ['template_id', 'html'],
                ],
            ],
            [
                'name' => 'load_font',
                'description' => 'Lädt eine Google-Schrift herunter und speichert sie lokal (DSGVO-konform). Danach im Layout als Schrift wählbar.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['family' => ['type' => 'string', 'description' => 'Google-Fonts-Name, z. B. „Inter" oder „Playfair Display"']],
                    'required' => ['family'],
                ],
            ],
            [
                'name' => 'list_media',
                'description' => 'Durchsucht die Mediathek nach Bildern (Name, Alt-Text, Titel), optional auf einen Ordner begrenzt. Liefert Bild-URLs zur direkten Verwendung in Blöcken.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'folder' => ['type' => 'string', 'description' => 'Ordnername (optional, exakt wie in der Liste)'],
                        'search' => ['type' => 'string', 'description' => 'Suchbegriff (optional)'],
                    ],
                ],
            ],
            [
                'name' => 'get_layout',
                'description' => 'Liest ein Layout (Kopf-/Fußzeile der Website): Name, Typ und Builder-JSON (visuell) bzw. HTML (klassisch). Vor jeder Layout-Änderung aufrufen.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['layout_id' => ['type' => 'integer']],
                    'required' => ['layout_id'],
                ],
            ],
            [
                'name' => 'update_layout',
                'description' => 'Ersetzt das Builder-JSON eines VISUELLEN Layouts – wirkt sofort auf allen Seiten mit diesem Layout. l-content muss genau einmal enthalten sein.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'layout_id' => ['type' => 'integer'],
                        'builder' => [
                            'type' => 'object',
                            'description' => 'Vollständiges Builder-JSON ({"rows": [...]}) inkl. der Layout-Blöcke.',
                            'properties' => ['rows' => ['type' => 'array']],
                            'required' => ['rows'],
                        ],
                    ],
                    'required' => ['layout_id', 'builder'],
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
