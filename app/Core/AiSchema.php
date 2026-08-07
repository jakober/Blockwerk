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

        // Shop-Abschnitt nur im Prompt, wenn der Shop aktiviert ist.
        $shopSection = \Core\Shop::enabled()
            ? "\n- **Shop** (list_shop_categories/create_shop_category/update_shop_category, list_shop_products/create_shop_product/update_shop_product): Produkte und Kategorien anlegen und pflegen. Preise immer in Euro (z. B. 19.90). Für ein Produkt in einer Kategorie zuerst list_shop_categories aufrufen und die passende category_id verwenden – oder die Kategorie vorher mit create_shop_category anlegen. Produktbilder kannst du mit generate_image erzeugen oder mit list_media aus der Mediathek holen und als image-URL übergeben. Du kannst pro Produkt auch **Staffelpreise** (tier_prices: ab Menge X günstigerer Stückpreis), **Varianten/Eigenschaften** (variants: z. B. Größe S/M/L oder Farbe, optional mit Preisaufschlag surcharge), **Cross-Selling** und **Zubehör** (cross_sell/accessories mit Produkt-IDs aus list_shop_products) sowie **SEO-Felder** (meta_title/meta_description für Kategorie und Produkt) setzen. Ein eigener **Steuersatz** je Produkt (tax_rate in %) überschreibt den Standardsatz.\n"
                . "  **Steuern** (set_shop_tax): Frag aktiv nach, ob der Betreiber Kleinunternehmer nach §19 UStG ist (mode „none“, keine MwSt.-Ausweisung) oder Bruttopreise inkl. MwSt. ausweisen will (mode „inclusive“, mit default_rate in %, üblich 19 oder 7) – nimm das nicht selbst an.\n"
                . "  **Rechtstexte**: Bei Shop-Aktivierung werden AGB und Widerrufsbelehrung automatisch mit Platzhaltertext angelegt (wie Impressum/Datenschutz) – lege sie nicht neu an, sondern weise den Nutzer bei Bedarf darauf hin, sie zu vervollständigen.\n"
                . "  **Rechnungen** entstehen automatisch zu jeder Bestellung inkl. PDF-Mailversand – dafür ist kein Tool nötig. **Lagerbestand** (stock je Produkt) schützt automatisch vor Überverkauf an der Kasse.\n"
                . "  **Gutscheine** (list_shop_coupons/create_shop_coupon/update_shop_coupon): Rabattcodes anlegen und ändern – prozentual (percent, 0–100) oder als fester Betrag in Euro (fixed), optional mit Mindestbestellwert, Gültigkeitszeitraum und Nutzungslimit. Vor Änderungen erst list_shop_coupons aufrufen, um die passende coupon_id zu finden.\n"
                . "  **Versandarten** (list_shipping/create_shipping/update_shipping): Du kannst Versandarten anlegen und ändern – pauschal oder **gewichtsabhängig** (weight_tiers: „bis X kg → Preis“) und auf bestimmte **Länder** begrenzt (countries mit deutschen Ländernamen, leer = alle). Beispiel: „bis 5 kg 20 €, bis 20 kg 50 € nur nach Deutschland“.\n"
                . "  **Nur zur Info, kein eigenes Werkzeug**: Merkliste, geräteübergreifender Warenkorb und Adressbuch sind reine Kundenselbstbedienung. **Produktbewertungen** entstehen automatisch nach einem echten Kauf und müssen im Backend freigeschaltet werden – du darfst **niemals selbst Bewertungen erfinden oder anlegen**. Rückerstattungen und Versand-Tracking werden vom Nutzer direkt bei der jeweiligen Bestellung im Backend verwaltet – dafür gibt es kein Tool.\n"
                . "  Weise den Nutzer darauf hin, dass der Shop unter „Shop-Einstellungen“ aktiviert und eine Hauptseite gewählt sein muss, damit Produkte auf der Website erscheinen; Zahlungsarten (Rechnung, Vorkasse, PayPal, SEPA-Lastschrift) sowie deren Zugangsdaten (z. B. PayPal-Zugangsdaten, SEPA-Gläubiger-ID/IBAN) richtet der Nutzer dort selbst ein."
            : '';

        return <<<PROMPT
Du bist der KI-Assistent von „Blockwerk Orange“, einem deutschen CMS. Du hilfst dem Betreiber der Website „{$siteName}“, Seiten und Inhalte zu erstellen. Du kennst die Architektur des Systems perfekt und arbeitest ausschließlich über die bereitgestellten Tools. Antworte immer auf Deutsch, freundlich und knapp.

## Inhalts-Format (Content-JSON)

Eine Seite besteht aus {"rows": [...]}. Jede Zeile (row) hat:
- "columns": Liste von Spalten. Jede Spalte: {"span": 1–12, "blocks": [...], optional "style": {...}}. Die Spannen einer Zeile sollten zusammen 12 ergeben (z. B. [12], [6,6], [4,4,4], [8,4]). Spalten-"style" gestaltet die einzelne Spalte (z. B. als farbige Karte): {"bg": "#rrggbb" ODER Paletten-Schlüssel "primary"/"accent"/"surface"/"page", "p": Innenabstand px, "align": "left|center|right", "valign": "top|center|bottom" (vertikale Ausrichtung bei unterschiedlich hohen Spalten), "radius": Ecken px}.
- optional "style": {"bg": Hintergrund, "width": ""|"full", "pt": px, "pb": px, "bp": px}. "bg" ist ENTWEDER ein Hex-Wert "#rrggbb" ODER ein Paletten-Schlüssel: "primary", "accent", "surface", "page" (folgt dann automatisch den Layout-Farben – bevorzuge die Palette!). "width":"full" lässt die Inhalte über die volle Browserbreite laufen. "pt"/"pb" = Innenabstand oben/unten in px (Sektionen: 40–80 wirkt gut).

Jeder Block: {"type": "...", "data": {...}}. Optional data._style = grafische Gestaltung DIESES einen Blocks (ohne CSS):
- "color": Textfarbe "#rrggbb" (z. B. weiße Schrift auf dunklem Hintergrund)
- "bg": Hintergrundfarbe des Blocks "#rrggbb"
- "radius": Eckenrundung in px (0 = eckig)
- "align": Ausrichtung des Inhalts "left"|"center"|"right" (zentriert auch Formulare und Buttons)
- "mt","mb","ml","mr": Außenabstand oben/unten/links/rechts in px; "p": Innenabstand in px
- "disp": Darstellung "inline" (nebeneinander) | "block" (untereinander/volle Zeile) – Buttons stehen von Haus aus nebeneinander
- "anim": Scroll-Animation ("fade","up","left","right","zoom")
- "malign","mmt","mmb","mp": Mobil-Überschreibungen
Setze diese Werte immer, wenn der Nutzer einem Block eine Hintergrund-/Schriftfarbe, runde Ecken oder eine Ausrichtung geben möchte.

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
- team: members:[{src,name,role,email,phone,text}], columns (2–4), zoom (0/1: Fotos per Klick in Lightbox vergrößern). email wird als mailto-Link, phone als tel-Link (je eigene Zeile) ausgegeben.
- pricing: plans:[{title,price,period,features (eine Leistung pro Zeile),button_text,button_url,highlight}]
- countdown: target ("JJJJ-MM-TT HH:MM"), title, expired_text
- social: links:[{network("facebook","instagram","x","youtube","linkedin","tiktok","whatsapp","mail","phone"),url}], size
- html: code | divider: – | spacer: height (px)

## Gestaltungsregeln für moderne Seiten

1. **Noch keine Seiten vorhanden** (siehe Live-Kontext: „noch keine Seiten“) **und der Auftrag nennt nur eine Einzelheit** (z. B. „Nutze die Farben aus meinem Logo“, „mach es blau“)? Dann lege trotzdem die **komplette Startseite** an – Hero mit Titelbild und Slogan, Leistungen, Über uns, Kontakt – plus Impressum und Datenschutz. Die Einzelheit ist eine **Vorgabe für die Gestaltung**, nicht der ganze Auftrag; frage dazu nicht nach. Eine Seite aus Logo und Slogan allein ist keine Website.
2. Starte Landingpages mit einem hero-Block (Zeile [12]) mit generiertem Bild, prägnantem Titel und Button.
3. Wechsle Sektions-Hintergründe ab: normale Zeilen und Zeilen mit style {"bg":"surface","pt":50,"pb":50} – so entsteht Rhythmus. Nutze die Palette, keine harten Hex-Farben.
4. Nutze 3-Karten-Muster ([4,4,4] mit heading h3 + text variant "infobox") für Vorteile/Leistungen, [6,6] für Text+Bild im Wechsel (Bild mit variant "shadow").
5. Texte: konkret, deutsch, kein Lorem ipsum, 2–4 Sätze pro Textblock, Überschriften-Hierarchie sauber (eine h1 pro Seite).
6. **Barrierefreiheit (verbindlich):** Jedes Bild bekommt einen **Alternativtext** (`alt`) – kurz beschreiben, was zu sehen ist; bei reiner Deko leer lassen. Genau **eine** Hauptüberschrift je Seite (der Hero-Titel), danach absteigende Ebenen ohne Sprünge. Bei Farbwahl auf **Kontrast ≥ 4,5:1** achten (kein helles Grau auf Weiß, keine dunkle Schrift auf dunklem Bild – Hero-Text braucht die Abdunklung). Sprechende Linktexte statt „hier klicken".
7. **Pflichtseiten:** Zu jeder Website gehören **Impressum** und **Datenschutzerklärung** als eigene Seiten, verlinkt im Menü bzw. in der Fußzeile. Beides ist in Deutschland vorgeschrieben (§ 5 DDG, Art. 13 DSGVO). Fehlen Angaben (z. B. die Anschrift), setze einen deutlich markierten Platzhalter wie `[Bitte Anschrift ergänzen]` und weise den Nutzer **einmal kurz** darauf hin, dass er beides prüfen muss. Nach dem Speichern einer Seite bekommst du einen Hinweis, falls eine der beiden Seiten noch fehlt – lege sie dann an.
8. **Logo:** Ist im Kopf noch kein Logo hinterlegt, frage den Nutzer einmal kurz, ob er eins hat (dann in die Mediathek laden und mit `set_logo` setzen) oder ob du eins entwerfen sollst (`generate_image`, schlicht, ohne Text im Bild, quadratisch) – bevor du das Design festlegst, damit die Farben dazu passen. **Diese Klärung ist nur eine Vorbereitung und ersetzt den Bauauftrag nicht:** Sobald der Nutzer geantwortet hat (Logo hochgeladen oder „bitte entwerfen“), baust du **direkt die Seite weiter** – nicht nur das Logo. `set_logo` wirkt in **jedem** Layout (auch klassisches HTML) – antworte NIE, du könntest das Logo nicht setzen, und verweise den Nutzer nicht auf den Baukasten oder auf manuelles Einbauen.
9. Generiere für zentrale Stellen (Hero, Text+Bild) Bilder per generate_image mit detaillierten fotografischen Prompts (Stil, Licht, Motiv – ohne Text im Bild). PRÜFE aber zuerst mit list_media, ob passende Bilder in der Mediathek liegen (spart Guthaben) – vor allem, wenn der Nutzer einen bestimmten Ordner nennt („nimm die Bilder aus Ordner X"), nutze GENAU diese Bilder.
10. Schließe Kontakt-/Landingpages mit einer Kontakt-Sektion ab (heading + form, gerne mit _style.align "center").
11. Setze dezente Scroll-Animationen ein (_style.anim): "up" oder "fade" für Sektions-Inhalte, z. B. bei Karten-Reihen die drei Karten mit "up". Nicht auf dem Hero, nicht bei jedem Block – sparsam wirkt hochwertig. Berücksichtige das auch bei Änderungen an bestehenden Seiten.
12. Einzelne Blöcke gestalten: Bittet der Nutzer, einem bestimmten Block eine Hintergrundfarbe, Schriftfarbe, runde Ecken oder eine Ausrichtung (links/mittig/rechts) zu geben, setze das gezielt über data._style dieses Blocks ("bg","color","radius","align"). Beispiel: eine hervorgehobene Info-Box = text-Block mit _style {"bg":"#0f172a","color":"#ffffff","radius":16,"p":24}. Bei Änderungswünschen an bestehenden Seiten die vorhandenen _style-Werte des Blocks beibehalten und nur die gewünschten ergänzen/ändern.

## Layouts (Kopf-/Fußzeile, gilt auf ALLEN Seiten)

Visuell gebaute Layouts haben ein Builder-JSON mit exakt derselben Struktur wie Seiten-Content ({"rows":[...]}), zusätzlich mit Layout-Blöcken:
- l-brand: {logo, show_name} – Logo & Website-Name. Das Logo setzt du am einfachsten mit set_logo (kein komplettes Layout-JSON nötig).
- l-menu: Hauptmenü (Daten unverändert lassen – wird über den Menü-Designer gepflegt)
- l-content: {} – Platzhalter für den Seiteninhalt, MUSS genau EINMAL vorkommen
- l-languages: {} – Sprachumschalter

Regeln für Layout-Änderungen ("überall auf der Website"):
1. IMMER zuerst get_layout aufrufen und das vorhandene Builder-JSON als Basis nehmen – Kopfzeile (l-brand/l-menu) und l-content nie entfernen oder verdoppeln.
2. Der Footer sind die Zeilen NACH der l-content-Zeile. "Über dem Footer auf allen Seiten" = neue Zeile direkt vor der ersten Footer-Zeile (nach l-content) einfügen.
3. update_layout mit dem VOLLSTÄNDIGEN neuen Builder-JSON aufrufen. Änderungen wirken sofort auf allen Seiten mit diesem Layout.
4. Klassische Layouts (HTML) änderst du mit set_layout_html: erst get_layout lesen, das vorhandene HTML als Basis nehmen und {{content}} genau einmal behalten. Nutzbare Platzhalter: {{content}}, {{title}}, {{site_name}}, {{logo}} (Logo der Website), {{base_url}}, {{year}}, {{menu}}, {{template:schlüssel}}. Verweise den Nutzer NICHT auf den visuellen Baukasten – du kannst beide Layout-Arten bearbeiten.
5. Schriften und Design-Farben eines Layouts setzt du mit set_layout_design (nicht über update_layout) – das gilt auch für klassische Layouts.

## Weitere Bereiche, die du verwalten kannst

- **News & Events** (create_post/update_post/list_posts): eigene Beiträge mit Titel, Kurzbeschreibung (excerpt), Inhalt (body = sauberes HTML), Beitragsbild und – bei Events – Beginn/Ende (Format „JJJJ-MM-TT HH:MM") und Ort. type ist "news" oder "event".
- **Globale Blöcke** (create_global_block/update_global_block/list_global_blocks): wiederverwendbare Inhaltsbereiche mit demselben Content-JSON wie Seiten. Werden über den „Globaler Block"-Block auf mehreren Seiten eingebettet – eine Änderung wirkt überall.
- **Templates** (create_template/update_template/list_templates): wiederverwendbare HTML-Bausteine mit Schlüssel (für {{template:schlüssel}}). Das Menü-Template „main-menu" NICHT hier ändern – dafür ist der Menü-Designer zuständig.
- **Schriften** (load_font, set_layout_design): load_font lädt eine Google-Schrift lokal (DSGVO). Mit set_layout_design weist du einem Layout Schriften direkt zu – je Slot (`heading`, `body` oder einzeln `h1`–`h6`) den Google-Fonts-Namen angeben; noch nicht installierte Schriften werden dabei automatisch geladen. Optional lassen sich damit auch die Design-Farben (`primary`,`accent`,`text`,`bg`,`surface` als #rrggbb) setzen. Du musst das NICHT mehr dem Nutzer überlassen – wähle passende Schriften und weise sie selbst zu.
- **Designs** (create_design): erstellt ein individuelles Gesamt-Design nach Beschreibung und aktiviert es. Wähle Farben, Tokens UND die Stilrichtung `component_style` passend zur Stimmung – nicht nur Farben ändern! `component_style` bestimmt das Aussehen ALLER Elemente (Karten, Zitate, Akkordeon …): „panel"=klar/umrandet, „soft"=rund/weich, „bold"=blockig/kantig, „editorial"=Serif/Magazin, „slant"=schräg/diagonal. Beispiele: „minimalistisch/groß" → component_style „bold", radius 0, button „sharp", hero 100, uppercase true; „verspielt/weich" → component_style „soft", radius 24+, button „pill", shadow „strong"; „edel/redaktionell" → component_style „editorial", heading_font „serif", header_layout „center"; „dynamisch/schräg" → component_style „slant". Das Design erscheint danach unter „Designs".
- **Online-Recherche** (fetch_url, download_image): Du kannst eine öffentliche Webseite abrufen (fetch_url), um sie als Vorlage zu nehmen und eine ähnliche Seite zu bauen, und einzelne Bilder herunterladen (download_image, nur auf ausdrücklichen Wunsch). WICHTIG: Übernimm fremde Texte NIE 1:1 – formuliere alles mit eigenen Worten. Weise den Nutzer aktiv darauf hin, dass fremde Inhalte und Bilder urheberrechtlich geschützt sein können und die Verantwortung für ihre Verwendung bei ihm liegt.{$shopSection}

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

    /** System-Prompt für die Planungs-Phase: nur planen, nichts ausführen. */
    public static function planPrompt(): string
    {
        return self::systemPrompt() . "\n\n## AKTUELLE AUFGABE: NUR PLANEN – NICHTS AUSFÜHREN\n"
            . 'Zerlege die Anfrage des Nutzers in klare, nacheinander ausführbare Schritte. Jeder Schritt ist '
            . 'eine zusammenhängende Einheit (z. B. „eine Seite anlegen", „ein Bild generieren", „das Menü anpassen", '
            . '„ein Produkt einpflegen"). Halte die Schritte sinnvoll grob – höchstens 8 –, deutsch, mit kurzem Titel '
            . 'und 1–2 Sätzen Detail. Markiere je Schritt mit „fast", ob ein schnelles/einfaches Modell genügt: '
            . 'einfache Schritte (kurze Texte, kleine Änderungen, ein einzelnes Feld setzen) → fast=true; '
            . 'anspruchsvolle Schritte (ganze Seite gestalten, Bilder generieren, komplexe Struktur/Design) → fast=false. '
            . 'Bei einer sehr kleinen Anfrage genügt ein einziger Schritt. Führe nichts aus; '
            . 'rufe ausschließlich das Werkzeug propose_plan mit den Schritten auf.';
    }

    /** Das einzige Werkzeug der Planungs-Phase. */
    public static function planTool(): array
    {
        return [[
            'name' => 'propose_plan',
            'description' => 'Schlägt einen Umsetzungsplan als geordnete Schrittliste vor. Führt selbst nichts aus.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'steps' => [
                        'type' => 'array',
                        'description' => 'Die Schritte in Ausführungsreihenfolge (höchstens 8).',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string', 'description' => 'Kurzer Titel des Schritts'],
                                'detail' => ['type' => 'string', 'description' => 'Was konkret gemacht wird (1–2 Sätze)'],
                                'fast' => ['type' => 'boolean', 'description' => 'true, wenn für diesen Schritt ein schnelles/einfaches Modell genügt (z. B. kurzer Text, kleine Änderung, ein einzelnes Feld). false bei anspruchsvollen Schritten (ganze Seite gestalten, Bildgenerierung, komplexe Struktur/JSON, Design).'],
                            ],
                            'required' => ['title'],
                        ],
                    ],
                ],
                'required' => ['steps'],
            ],
        ]];
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

        $all = [
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
                'name' => 'set_layout_html',
                'description' => 'Ersetzt das HTML eines KLASSISCHEN Layouts vollständig – wirkt sofort auf allen Seiten mit diesem Layout. Vorher get_layout aufrufen und das vorhandene HTML als Basis nehmen. {{content}} muss genau einmal enthalten bleiben; nutzbare Platzhalter: {{content}}, {{title}}, {{site_name}}, {{logo}}, {{base_url}}, {{year}}, {{menu}}, {{template:schlüssel}}.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'layout_id' => ['type' => 'integer'],
                        'html' => ['type' => 'string', 'description' => 'Vollständiges Layout-HTML (<!doctype html> … </html>) inkl. {{content}}.'],
                    ],
                    'required' => ['layout_id', 'html'],
                ],
            ],
            [
                'name' => 'set_logo',
                'description' => 'Setzt das Logo der Website auf eine Bild-URL – funktioniert in JEDEM Layout (visueller Baukasten wie klassisches HTML) und erscheint im Kopf aller Seiten. Bei klassischen Layouts wird der Platzhalter {{logo}} automatisch im Kopfbereich ergänzt.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'image_url' => ['type' => 'string', 'description' => 'URL des Logo-Bildes (z. B. aus download_image, generate_image oder list_media).'],
                        'show_name' => ['type' => 'boolean', 'description' => 'Optional: ob der Website-Name zusätzlich neben dem Logo erscheint (Standard: unverändert). Bei einem Logo mit Schriftzug meist false.'],
                        'layout_id' => ['type' => 'integer', 'description' => 'Optional – Standard ist das Standard-Layout.'],
                    ],
                    'required' => ['image_url'],
                ],
            ],
            [
                'name' => 'set_layout_design',
                'description' => 'Weist einem Layout Schriften und optional Design-Farben zu (Layout-Design, nicht die Struktur). Noch nicht installierte Google-Schriften werden automatisch geladen. Wirkt sofort auf allen Seiten mit diesem Layout.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'layout_id' => ['type' => 'integer'],
                        'fonts' => [
                            'type' => 'object',
                            'description' => 'Google-Fonts-Namen je Slot (alle optional). „heading" = alle Überschriften, „body" = Fließtext; h1–h6 überschreiben einzelne Ebenen.',
                            'properties' => [
                                'heading' => ['type' => 'string'],
                                'body' => ['type' => 'string'],
                                'h1' => ['type' => 'string'],
                                'h2' => ['type' => 'string'],
                                'h3' => ['type' => 'string'],
                                'h4' => ['type' => 'string'],
                                'h5' => ['type' => 'string'],
                                'h6' => ['type' => 'string'],
                            ],
                        ],
                        'colors' => [
                            'type' => 'object',
                            'description' => 'Design-Farben als #rrggbb (alle optional).',
                            'properties' => [
                                'primary' => ['type' => 'string'],
                                'accent' => ['type' => 'string'],
                                'text' => ['type' => 'string'],
                                'bg' => ['type' => 'string'],
                                'surface' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'required' => ['layout_id'],
                ],
            ],
            [
                'name' => 'list_shop_categories',
                'description' => 'Listet die Shop-Kategorien (id, Name, übergeordnete Kategorie). Vor dem Anlegen von Produkten/Unterkategorien aufrufen, um passende category_id/parent_id zu finden.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'create_shop_category',
                'description' => 'Legt eine Shop-Kategorie an. Für eine Unterkategorie parent_id angeben (aus list_shop_categories).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'parent_id' => ['type' => 'integer', 'description' => 'ID der Oberkategorie (optional)'],
                        'description' => ['type' => 'string', 'description' => 'Kurztext oben auf der Kategorieseite (optional)'],
                        'image' => ['type' => 'string', 'description' => 'Bild-URL (optional)'],
                        'meta_title' => ['type' => 'string', 'description' => 'SEO-Titel (optional, leer = Kategoriename)'],
                        'meta_description' => ['type' => 'string', 'description' => 'SEO-Beschreibung für Suchergebnisse (optional, ~120–160 Zeichen)'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'update_shop_category',
                'description' => 'Ändert eine bestehende Shop-Kategorie (per id aus list_shop_categories). Nur übergebene Felder werden geändert.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'category_id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'parent_id' => ['type' => 'integer', 'description' => 'ID der Oberkategorie (optional)'],
                        'description' => ['type' => 'string'],
                        'image' => ['type' => 'string'],
                        'meta_title' => ['type' => 'string'],
                        'meta_description' => ['type' => 'string'],
                    ],
                    'required' => ['category_id'],
                ],
            ],
            [
                'name' => 'list_shop_products',
                'description' => 'Listet Shop-Produkte (id, Name, Preis, Kategorie). Optional mit Suchbegriff.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['search' => ['type' => 'string', 'description' => 'Suchbegriff (optional)']],
                ],
            ],
            [
                'name' => 'create_shop_product',
                'description' => 'Legt ein Shop-Produkt an (aktiv/sichtbar). Preise in Euro (z. B. 19.90). Der Shop muss in den Shop-Einstellungen aktiviert sein, damit Produkte auf der Website erscheinen.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'price' => ['type' => 'number', 'description' => 'Verkaufspreis in Euro, z. B. 19.90'],
                        'category_id' => ['type' => 'integer', 'description' => 'Kategorie-ID (aus list_shop_categories, optional)'],
                        'sku' => ['type' => 'string', 'description' => 'Artikelnummer (optional)'],
                        'short_desc' => ['type' => 'string', 'description' => 'Kurzbeschreibung für Listen/Kacheln (optional)'],
                        'description' => ['type' => 'string', 'description' => 'Ausführliche Beschreibung als HTML (<p>, <ul>…) (optional)'],
                        'image' => ['type' => 'string', 'description' => 'Produktbild-URL (optional, z. B. aus list_media oder generate_image)'],
                        'compare_price' => ['type' => 'number', 'description' => 'Streichpreis für Angebote in Euro (optional)'],
                        'stock' => ['type' => 'integer', 'description' => 'Lagerbestand (optional, leer = unbegrenzt)'],
                        'weight' => ['type' => 'number', 'description' => 'Gewicht in kg (optional, für gewichtsabhängigen Versand, z. B. 2.5)'],
                        'featured' => ['type' => 'integer', 'description' => '1 = auf der Shop-Startseite empfehlen'],
                        'tax_rate' => ['type' => 'number', 'description' => 'Eigener Steuersatz in % für dieses Produkt (optional, z. B. 7) – leer = Standardsatz aus den Shop-Einstellungen'],
                        'meta_title' => ['type' => 'string', 'description' => 'SEO-Titel (optional, leer = Produktname)'],
                        'meta_description' => ['type' => 'string', 'description' => 'SEO-Beschreibung für Suchergebnisse (optional, ~120–160 Zeichen)'],
                        'tier_prices' => [
                            'type' => 'array',
                            'description' => 'Staffelpreise (optional): ab Menge „min" (>1) gilt der Stückpreis „price" in Euro.',
                            'items' => ['type' => 'object', 'properties' => ['min' => ['type' => 'integer'], 'price' => ['type' => 'number']]],
                        ],
                        'variants' => [
                            'type' => 'array',
                            'description' => 'Produkteigenschaften/Varianten (optional), z. B. Größe oder Farbe. Je Gruppe „name" plus Auswahlmöglichkeiten „choices" mit „label" und optionalem Preisaufschlag „surcharge" in Euro.',
                            'items' => ['type' => 'object', 'properties' => [
                                'name' => ['type' => 'string'],
                                'choices' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['label' => ['type' => 'string'], 'surcharge' => ['type' => 'number']]]],
                            ]],
                        ],
                        'cross_sell' => ['type' => 'array', 'description' => 'Cross-Selling: Produkt-IDs verwandter Produkte (optional).', 'items' => ['type' => 'integer']],
                        'accessories' => ['type' => 'array', 'description' => 'Zubehör: Produkt-IDs (optional).', 'items' => ['type' => 'integer']],
                    ],
                    'required' => ['name', 'price'],
                ],
            ],
            [
                'name' => 'update_shop_product',
                'description' => 'Ändert ein bestehendes Produkt (per id aus list_shop_products). Nur übergebene Felder werden geändert; Preise in Euro.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'price' => ['type' => 'number'],
                        'category_id' => ['type' => 'integer'],
                        'short_desc' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'image' => ['type' => 'string'],
                        'compare_price' => ['type' => 'number'],
                        'stock' => ['type' => 'integer'],
                        'weight' => ['type' => 'number', 'description' => 'Gewicht in kg (optional, z. B. 2.5)'],
                        'featured' => ['type' => 'integer'],
                        'active' => ['type' => 'integer', 'description' => '1 = sichtbar, 0 = ausgeblendet'],
                        'tax_rate' => ['type' => 'number', 'description' => 'Eigener Steuersatz in % (optional, z. B. 7) – leer/weglassen lässt den vorhandenen Wert unverändert'],
                        'meta_title' => ['type' => 'string'],
                        'meta_description' => ['type' => 'string'],
                        'tier_prices' => [
                            'type' => 'array',
                            'description' => 'Staffelpreise (optional, ersetzt vorhandene): ab Menge „min" (>1) Stückpreis „price" in Euro.',
                            'items' => ['type' => 'object', 'properties' => ['min' => ['type' => 'integer'], 'price' => ['type' => 'number']]],
                        ],
                        'variants' => [
                            'type' => 'array',
                            'description' => 'Varianten/Eigenschaften (optional, ersetzt vorhandene): je Gruppe „name" + „choices" mit „label" und optionalem „surcharge" in Euro.',
                            'items' => ['type' => 'object', 'properties' => [
                                'name' => ['type' => 'string'],
                                'choices' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['label' => ['type' => 'string'], 'surcharge' => ['type' => 'number']]]],
                            ]],
                        ],
                        'cross_sell' => ['type' => 'array', 'description' => 'Cross-Selling Produkt-IDs (optional, ersetzt vorhandene).', 'items' => ['type' => 'integer']],
                        'accessories' => ['type' => 'array', 'description' => 'Zubehör Produkt-IDs (optional, ersetzt vorhandene).', 'items' => ['type' => 'integer']],
                    ],
                    'required' => ['product_id'],
                ],
            ],
            [
                'name' => 'list_shipping',
                'description' => 'Listet die Versandarten (id, Name, Länder, Gewichtsstaffeln, Preis). Vor dem Ändern aufrufen.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'create_shipping',
                'description' => 'Legt eine Versandart an. Preise in Euro. Optional gewichts- und länderabhängig: „weight_tiers" = Gewichtsstaffeln (ab-Menge nach oben, „bis X kg → Preis"), „countries" = Länder für die die Versandart gilt (leer = alle). Ohne Staffeln gilt der Pauschalpreis „price".',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'price' => ['type' => 'number', 'description' => 'Pauschalpreis in Euro (gilt, wenn keine Gewichtsstaffeln angegeben sind)'],
                        'free_from' => ['type' => 'number', 'description' => 'Versandkostenfrei ab dieser Warenkorbsumme in Euro (optional)'],
                        'description' => ['type' => 'string', 'description' => 'Kurzbeschreibung (optional)'],
                        'countries' => ['type' => 'array', 'description' => 'Länder (deutsche Namen), für die diese Versandart gilt. Leer/weglassen = alle Länder.', 'items' => ['type' => 'string']],
                        'weight_tiers' => [
                            'type' => 'array',
                            'description' => 'Gewichtsstaffeln: bis „up_to_kg" Kilogramm gilt „price" Euro. Beispiel: [{up_to_kg:5, price:20},{up_to_kg:20, price:50}].',
                            'items' => ['type' => 'object', 'properties' => ['up_to_kg' => ['type' => 'number'], 'price' => ['type' => 'number']]],
                        ],
                        'active' => ['type' => 'integer', 'description' => '1 = aktiv (Standard), 0 = deaktiviert'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'update_shipping',
                'description' => 'Ändert eine Versandart (per id aus list_shipping). Nur übergebene Felder werden geändert; countries/weight_tiers ersetzen die vorhandenen.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'shipping_id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'price' => ['type' => 'number'],
                        'free_from' => ['type' => 'number'],
                        'description' => ['type' => 'string'],
                        'countries' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'weight_tiers' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['up_to_kg' => ['type' => 'number'], 'price' => ['type' => 'number']]]],
                        'active' => ['type' => 'integer'],
                    ],
                    'required' => ['shipping_id'],
                ],
            ],
            [
                'name' => 'set_shop_tax',
                'description' => 'Stellt den Steuer-Modus des Shops ein: „none" = Kleinunternehmer nach §19 UStG (keine MwSt.-Ausweisung), „inclusive" = Bruttopreise inkl. MwSt. mit Ausweisung auf Rechnung. Frag den Nutzer aktiv, welcher Fall zutrifft, statt es anzunehmen.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'mode' => ['type' => 'string', 'enum' => ['none', 'inclusive'], 'description' => '„none" = Kleinunternehmer §19 UStG, „inclusive" = Bruttopreise inkl. MwSt.'],
                        'default_rate' => ['type' => 'number', 'description' => 'Standard-Steuersatz in %, z. B. 19 (nur bei „inclusive" relevant, Standard 19)'],
                    ],
                    'required' => ['mode'],
                ],
            ],
            [
                'name' => 'list_shop_coupons',
                'description' => 'Listet die Rabatt-Gutscheine (id, Code, Typ, Wert, Gültigkeit). Vor dem Ändern eines Gutscheins aufrufen.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'create_shop_coupon',
                'description' => 'Legt einen Rabatt-Gutschein für den Shop an. Der Code wird automatisch großgeschrieben und muss eindeutig sein.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'string', 'description' => 'Gutscheincode, z. B. „WILLKOMMEN10" (wird automatisch großgeschrieben)'],
                        'type' => ['type' => 'string', 'enum' => ['percent', 'fixed'], 'description' => '„percent" = Prozent-Rabatt, „fixed" = fester Euro-Betrag'],
                        'value' => ['type' => 'number', 'description' => 'Bei „percent": 0–100 (z. B. 10 für 10 %). Bei „fixed": Euro-Betrag (z. B. 5.00).'],
                        'min_subtotal' => ['type' => 'number', 'description' => 'Mindest-Warenkorbsumme in Euro, ab der der Gutschein gilt (optional)'],
                        'starts_at' => ['type' => 'string', 'description' => 'Gültig ab, Format „JJJJ-MM-TT" oder „JJJJ-MM-TT HH:MM" (optional)'],
                        'ends_at' => ['type' => 'string', 'description' => 'Gültig bis, Format „JJJJ-MM-TT" oder „JJJJ-MM-TT HH:MM" (optional)'],
                        'usage_limit' => ['type' => 'integer', 'description' => 'Maximale Anzahl Einlösungen insgesamt (optional, leer = unbegrenzt)'],
                        'active' => ['type' => 'integer', 'description' => '1 = aktiv (Standard), 0 = deaktiviert'],
                    ],
                    'required' => ['code', 'type', 'value'],
                ],
            ],
            [
                'name' => 'update_shop_coupon',
                'description' => 'Ändert einen bestehenden Gutschein (per id aus list_shop_coupons). Nur übergebene Felder werden geändert.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'coupon_id' => ['type' => 'integer'],
                        'code' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'enum' => ['percent', 'fixed']],
                        'value' => ['type' => 'number', 'description' => 'Bei „percent": 0–100. Bei „fixed": Euro-Betrag.'],
                        'min_subtotal' => ['type' => 'number'],
                        'starts_at' => ['type' => 'string'],
                        'ends_at' => ['type' => 'string'],
                        'usage_limit' => ['type' => 'integer'],
                        'active' => ['type' => 'integer'],
                    ],
                    'required' => ['coupon_id'],
                ],
            ],
            [
                'name' => 'create_design',
                'description' => 'Erstellt ein individuelles Gesamt-Design nach Beschreibung, speichert es unter „Designs" und aktiviert es sofort (überschreibt das Standard-Layout; Inhalte bleiben). Über die Tokens steuerst du die komplette Optik: Rundungen, Hero-Höhe, Abstände, Schriftstil, Button-Form, Schatten. Wähle Farben und Tokens passend zur gewünschten Stimmung (z. B. „minimalistisch, groß, kantig" → radius 0, button sharp, hero 100, uppercase true).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Name des Designs, z. B. „Sommerlich frisch"'],
                        'description' => ['type' => 'string', 'description' => 'Kurzbeschreibung des Looks'],
                        'colors' => [
                            'type' => 'object',
                            'description' => 'Farbschema als Hex-Werte (#rrggbb).',
                            'properties' => [
                                'primary' => ['type' => 'string'], 'accent' => ['type' => 'string'],
                                'text' => ['type' => 'string'], 'bg' => ['type' => 'string'], 'surface' => ['type' => 'string'],
                            ],
                            'required' => ['primary', 'accent', 'text', 'bg', 'surface'],
                        ],
                        'header' => [
                            'type' => 'object',
                            'description' => 'Kopfbereich: Hintergrund (Hex oder CSS-Verlauf) und Textfarbe (Hex).',
                            'properties' => ['bg' => ['type' => 'string'], 'text' => ['type' => 'string']],
                        ],
                        'style' => [
                            'type' => 'object',
                            'description' => 'Design-Tokens für die Gesamt-Optik.',
                            'properties' => [
                                'radius' => ['type' => 'integer', 'description' => 'Eckenrundung px (0 = kantig, 26 = sehr rund)'],
                                'hero' => ['type' => 'integer', 'description' => 'Hero-Höhe in % Bildschirmhöhe (30–100; 100 = Vollbild)'],
                                'container' => ['type' => 'integer', 'description' => 'Max. Inhaltsbreite px (800–1400)'],
                                'section' => ['type' => 'integer', 'description' => 'Innenabstand farbiger Sektionen px (0–140)'],
                                'shadow' => ['type' => 'string', 'enum' => ['none', 'soft', 'strong']],
                                'scale' => ['type' => 'number', 'description' => 'Basis-Schriftgröße px (14–22)'],
                                'heading_weight' => ['type' => 'integer', 'description' => 'Überschriften-Stärke (400–900)'],
                                'heading_spacing' => ['type' => 'string', 'description' => 'Überschriften-Laufweite, z. B. „-.5px" oder „0"'],
                                'uppercase' => ['type' => 'boolean', 'description' => 'Überschriften in Großbuchstaben'],
                                'heading_font' => ['type' => 'string', 'enum' => ['sans', 'serif', 'mono']],
                                'button' => ['type' => 'string', 'enum' => ['round', 'pill', 'sharp']],
                                'header_layout' => ['type' => 'string', 'enum' => ['bar', 'center'], 'description' => '„bar" = Marke links/Menü rechts, „center" = Marke zentriert, Menü darunter'],
                                'component_style' => ['type' => 'string', 'enum' => ['panel', 'soft', 'bold', 'editorial', 'slant'], 'description' => 'Stilrichtung ALLER Inhaltselemente (Karten, Zitate, Akkordeon usw.): „panel"=klar/umrandet, „soft"=rund/weich, „bold"=blockig/kantig/Versal, „editorial"=Serif/Magazin, „slant"=schräg/diagonal (Sektionen & Hero bekommen automatisch Schrägen). Passend zur Stimmung wählen!'],
                            ],
                        ],
                    ],
                    'required' => ['name', 'colors'],
                ],
            ],
            [
                'name' => 'fetch_url',
                'description' => 'Ruft eine öffentliche Webseite ab und liefert Titel, Beschreibung, Überschriften, Fließtext und gefundene Bild-URLs zurück – als Vorlage/Recherche, um eine ähnliche Seite zu bauen. Baue Inhalte mit EIGENEN, umformulierten Texten nach (nicht 1:1 kopieren). Weise den Nutzer darauf hin, dass fremde Inhalte urheberrechtlich geschützt sein können und die Verantwortung bei ihm liegt.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['url' => ['type' => 'string', 'description' => 'Vollständige Adresse inkl. https://']],
                    'required' => ['url'],
                ],
            ],
            [
                'name' => 'download_image',
                'description' => 'Lädt ein Bild von einer öffentlichen Bild-URL herunter, speichert es in der Mediathek und liefert die lokale Bild-URL zur Verwendung in Blöcken. Nur mit ausdrücklichem Wunsch des Nutzers verwenden. Urheberrecht beachten – die Verantwortung liegt beim Nutzer.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => ['type' => 'string', 'description' => 'Direkte Bild-URL (aus fetch_url)'],
                        'filename' => ['type' => 'string', 'description' => 'Sprechender Dateiname ohne Endung'],
                    ],
                    'required' => ['url'],
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

        // Shop-Werkzeuge nur anbieten, wenn der Shop aktiviert ist.
        if (!\Core\Shop::enabled()) {
            $shopTools = ['list_shop_categories', 'create_shop_category', 'update_shop_category',
                'list_shop_products', 'create_shop_product', 'update_shop_product',
                'list_shipping', 'create_shipping', 'update_shipping',
                'set_shop_tax', 'list_shop_coupons', 'create_shop_coupon', 'update_shop_coupon'];
            $all = array_values(array_filter($all, static fn (array $t): bool => !in_array($t['name'], $shopTools, true)));
        }

        return $all;
    }
}
