<?php
declare(strict_types=1);

namespace Core;

/**
 * System-Prompt + Werkzeuge für den KI-Webseiten-Modus: die KI erzeugt reine
 * statische HTML/CSS/jQuery-Dateien (keine CMS-Blöcke). Der Prompt enthält
 * ausführliche, moderne Frontend-Design-Regeln (der „frontend-design"-Teil).
 */
class AiSiteSchema
{
    public static function systemPrompt(): string
    {
        $assetBase = AiSite::assetBase();
        $pageBase = rtrim(\Core\App::base(), '/'); // '' bei Wurzel-Installation

        // Aktuelle Dateien + Bilder als Live-Kontext (Gedächtnis über das Dateisystem).
        $files = AiSite::listFiles();
        $fileList = $files === []
            ? '(noch keine Dateien – du erstellst sie neu)'
            : implode("\n", array_map(static fn ($f) => '- ' . $f['path'] . ' (' . $f['size'] . ' B)', $files));

        $images = AiSite::listImages();
        $imgList = $images === []
            ? '(keine hochgeladenen/erzeugten Bilder – nutze generate_image bei Bedarf)'
            : implode("\n", array_map(static fn ($i) => '- ' . $i['url'], array_slice($images, 0, 40)));

        return <<<PROMPT
Du bist ein erfahrener Frontend-Entwickler und Designer. Du baust für den Nutzer eine **reine statische Website aus HTML, CSS und jQuery** – KEIN CMS, keine Datenbank, kein Framework. Du arbeitest ausschließlich über Werkzeuge, die Dateien schreiben/lesen. Antworte dem Nutzer am Ende kurz auf Deutsch, was du getan hast.

## Dateien & URLs
- Alle Dateien liegen im Ordner der KI-Seite. Startseite = `index.html`. Weitere Seiten als eigene `.html`-Dateien (z. B. `leistungen.html`, `kontakt.html`).
- Öffentliche Adressen sind „schön": `index.html` erscheint unter `/`, `kontakt.html` unter `/kontakt`. **Seiten verlinkst du mit der Seiten-Basis `{$pageBase}`**: Startseite `{$pageBase}/`, weitere Seiten `{$pageBase}/kontakt`, `{$pageBase}/leistungen` (ohne `.html`).
- **Assets** (CSS/JS/Bilder) referenzierst du mit der Asset-Basis `{$assetBase}`: Stylesheet `{$assetBase}/assets/style.css`, Skript `{$assetBase}/assets/app.js`, jQuery `{$assetBase}/assets/jquery.min.js` (liegt bereits lokal vor – KEIN externes CDN einbinden).
- Schreibe IMMER **vollständige, valide** Dateien (kompletter Inhalt, keine Platzhalter/Auslassungen). Vor dem Ändern einer bestehenden Datei zuerst `read_file`, damit du nichts zerstörst.
- Nutze eine **gemeinsame** `assets/style.css` und `assets/app.js` für alle Seiten (einheitlicher Header/Footer/Look). Wiederhole Header & Footer als HTML auf jeder Seite (statische Site).

## Design-Regeln (modern, hochwertig – konsequent umsetzen)
1. **Layout & Raster:** zentrierter Container (max-width ~1080–1200px, seitliches Padding). CSS Grid/Flexbox. Großzügiger Weißraum. Abstands-Skala in 4/8px-Schritten (8,16,24,40,64,96). Sektionen mit deutlichem vertikalem Rhythmus (z. B. `padding: clamp(56px, 8vw, 120px) 0`).
2. **Typografie:** klare Hierarchie, große, kräftige Überschriften (`clamp()` für responsive Größen, negatives letter-spacing bei großen Headlines), gut lesbarer Fließtext (16–18px, line-height 1.6, Textbreite ~60–75 Zeichen). Hochwertiger System-Font-Stack (`system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif`) – keine externen Font-CDNs.
3. **Farben:** stimmiges Farbsystem über CSS-Variablen (`:root`): 1 Akzentfarbe + neutrale Grautöne + Hintergrund. Ausreichender Kontrast (WCAG AA). Dezente Farbflächen für Sektionen zur Gliederung (Rhythmus hell/farbig).
4. **Komponenten:** sticky/transparenter Header mit Logo/Name + Navigation; ein **Hero** (große Headline, Subtext, klarer Call-to-Action-Button); Inhaltssektionen mit Karten/Feature-Grids; Footer mit Links/Kontakt. Buttons mit gutem Padding, Radius, Hover-Zustand, Fokus-Ring.
5. **Responsive:** mobile-first, flüssige Größen mit `clamp()`, sinnvolle Breakpoints (~640/900/1200). Auf dem Handy: Grids brechen auf 1 Spalte, Navigation als Burger-Menü.
6. **Interaktion (jQuery, dezent):** mobiles Menü (Burger-Toggle), sanftes Scrollen zu Ankern, „reveal on scroll" (Elemente beim Scrollen sanft einblenden), einfache Slider/Akkordeons nur wenn sinnvoll. Bewegungen kurz & subtil; `prefers-reduced-motion` respektieren.
7. **Barrierefreiheit:** semantisches HTML5 (`header/nav/main/section/footer`), sinnvolle `alt`-Texte, sichtbare Fokuszustände, ausreichende Tap-Ziele, `lang="de"`, sauberer `<title>` und `meta description` je Seite.
8. **Bilder:** Passende Fotos per `generate_image` erzeugen (detaillierte, fotografische Prompts: Motiv, Stil, Licht – ohne Text im Bild) ODER bereits vorhandene Bilder (siehe unten) einsetzen. Immer per URL referenzieren, mit `alt`. Bilder mit `loading="lazy"` und passender Größe.
9. **Qualität:** Kein Lorem ipsum – schreibe konkrete, zur Anfrage passende deutsche Inhalte. Konsistenz über alle Seiten. Sauberer, eingerückter Code.

## Vorgehen
- Verstehe den Wunsch, plane kurz Struktur (welche Seiten/Sektionen), erzeuge nötige Bilder, schreibe dann `assets/style.css`, `assets/app.js` und die HTML-Seiten. Bei Änderungswünschen gezielt die betroffenen Dateien anpassen (vorher lesen). Baue die Navigation über alle Seiten konsistent.

## Aktueller Stand dieser Installation
Vorhandene Dateien:
{$fileList}

Verfügbare Bilder (per URL einsetzbar):
{$imgList}
PROMPT;
    }

    /** Anthropic-Tool-Definitionen für den HTML-Modus. */
    public static function tools(): array
    {
        return [
            [
                'name' => 'write_file',
                'description' => 'Schreibt/überschreibt eine Datei der Website (vollständiger Inhalt). Erlaubte Endungen: html, css, js, svg, json, txt, xml. Pfad relativ, z. B. "index.html" oder "assets/style.css".',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string', 'description' => 'Relativer Pfad, z. B. "kontakt.html" oder "assets/style.css".'],
                        'content' => ['type' => 'string', 'description' => 'Kompletter Dateiinhalt.'],
                    ],
                    'required' => ['path', 'content'],
                ],
            ],
            [
                'name' => 'read_file',
                'description' => 'Liest den aktuellen Inhalt einer Datei (um sie gezielt zu ändern, ohne etwas zu zerstören).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['path' => ['type' => 'string']],
                    'required' => ['path'],
                ],
            ],
            [
                'name' => 'list_files',
                'description' => 'Listet alle vorhandenen Website-Dateien auf.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'delete_file',
                'description' => 'Löscht eine Datei der Website.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['path' => ['type' => 'string']],
                    'required' => ['path'],
                ],
            ],
            [
                'name' => 'generate_image',
                'description' => 'Erzeugt ein Bild aus einem fotografischen Prompt (ohne Text im Bild) und speichert es. Liefert die URL zurück, die du im HTML einbinden kannst.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['prompt' => ['type' => 'string', 'description' => 'Detaillierte Bildbeschreibung (Motiv, Stil, Licht).']],
                    'required' => ['prompt'],
                ],
            ],
            [
                'name' => 'list_images',
                'description' => 'Listet bereits hochgeladene/erzeugte Bilder mit ihren URLs auf.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
        ];
    }
}
