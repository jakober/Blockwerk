/**
 * Drag-&-Drop-Inhalts-Editor mit WYSIWYG-Vorschau.
 *
 * Die Blöcke im Canvas werden SERVERSEITIG gerendert (POST /admin/preview/blocks,
 * gleiche Render-Logik wie das Frontend) – die Seite sieht im Editor also genauso
 * aus wie live. Struktur: state.rows[] → columns[] (span 12er-Raster) → blocks[].
 *
 * Neue Block-Typen: Eintrag in blockDefs ergänzen und serverseitig in
 * app/Core/BlockRegistry.php registrieren.
 *
 * Jeder Block hat zusätzlich unter data._style grafische Einstellungen ohne CSS
 * (Abstände, Innenabstand, Farben, Ausrichtung, Eckenrundung); Zeilen haben
 * row.style (vollbreite Hintergrundfarbe, Innenabstände). Eigenes CSS pro Seite
 * liegt in state.css.
 */
(function () {
    'use strict';

    const root = document.getElementById('editor');
    if (!root) return;

    const saveUrl = root.dataset.saveUrl;
    const previewUrl = root.dataset.previewUrl;
    const csrf = root.dataset.csrf;

    let state = { rows: [] };
    try {
        const initial = JSON.parse(document.getElementById('editor-data').textContent);
        if (initial && Array.isArray(initial.rows)) state = initial;
        if (typeof state.css !== 'string') state.css = state.css || '';
    } catch (e) { state.css = ''; }

    let uidCounter = 0;
    const uid = (prefix) => prefix + '-' + Date.now().toString(36) + '-' + (uidCounter++);

    const editorMode = root.dataset.mode || 'page';

    const VARIANTS = {
        heading: [['standard', 'Standard'], ['accent-line', 'Mit Akzentlinie'], ['boxed', 'Farbig hinterlegt'], ['centered', 'Zentriert']],
        text: [['standard', 'Standard'], ['infobox', 'Infobox'], ['note', 'Hinweis (Akzentfarbe)']],
        image: [['standard', 'Standard'], ['frame', 'Mit Rahmen'], ['shadow', 'Mit Schatten'], ['round', 'Stark gerundet']],
        gallery: [['standard', 'Standard'], ['cards', 'Karten'], ['seamless', 'Randlos dicht']],
        quote: [['standard', 'Standard'], ['card', 'Karte'], ['big', 'Groß & zentriert']],
        accordion: [['standard', 'Standard'], ['cards', 'Karten']],
    };
    const variantField = (type) => ({ key: 'variant', label: 'Designvorlage', type: 'select', options: VARIANTS[type] });

    // Layout-Baukasten: Spezialblöcke für Kopf-/Fußzeile & Inhaltsbereich.
    const layoutDefs = {
        'l-brand': {
            label: 'Logo & Name', icon: '★',
            defaults: { logo: '', show_name: 1 },
            fields: [
                { key: 'logo', label: 'Logo (optional)', type: 'image' },
                { key: 'show_name', label: 'Website-Namen anzeigen', type: 'checkbox' },
            ],
        },
        'l-menu': {
            label: 'Menü', icon: '☰',
            defaults: { variant: 'dropdown', align: 'left', font_size: 16, item_padding: 14, gap: 4, transform: 'normal', color: '', dropdown_bg: '', dropdown_text: '', mega_full: 0, breakpoint: 900 },
            fields: [
                { key: 'variant', label: 'Menü-Vorlage', type: 'select', options: [['dropdown', 'Dropdown (Hover)'], ['mega', 'Mega-Menü'], ['vertical', 'Vertikal'], ['simple', 'Nur oberste Ebene']] },
                { key: 'align', label: 'Ausrichtung', type: 'select', options: [['left', 'Links'], ['center', 'Zentriert'], ['right', 'Rechts']] },
                { key: 'font_size', label: 'Schriftgröße (px)', type: 'number' },
                { key: 'item_padding', label: 'Abstand in den Menüpunkten (px)', type: 'number' },
                { key: 'gap', label: 'Abstand zwischen Menüpunkten (px)', type: 'number' },
                { key: 'transform', label: 'Schreibweise', type: 'select', options: [['normal', 'Normal'], ['uppercase', 'GROSSBUCHSTABEN']] },
                { key: 'color', label: 'Textfarbe', type: 'colorclear' },
                { key: 'dropdown_bg', label: 'Hintergrund des Aufklapp-Menüs', type: 'colorclear' },
                { key: 'dropdown_text', label: 'Textfarbe im Aufklapp-Menü', type: 'colorclear' },
                { key: 'mega_full', label: 'Mega-Menü über die volle Seitenbreite', type: 'checkbox' },
                { key: 'breakpoint', label: 'Mobiles Menü ab Bildschirmbreite (px, 0 = nie)', type: 'number' },
            ],
        },
        'l-content': {
            label: 'Inhaltsbereich', icon: '▣',
            defaults: {},
            fields: [],
        },
        'l-languages': {
            label: 'Sprachumschalter', icon: '🌐',
            defaults: {},
            fields: [],
        },
    };

    let blockDefs = {
        heading: {
            label: 'Überschrift', icon: 'H',
            defaults: { text: 'Neue Überschrift', level: 'h2', variant: 'standard' },
            fields: [
                { key: 'text', label: 'Text', type: 'text' },
                { key: 'level', label: 'Größe', type: 'select', options: [['h1', 'H1 – sehr groß'], ['h2', 'H2 – groß'], ['h3', 'H3 – mittel'], ['h4', 'H4 – klein']] },
                variantField('heading'),
            ],
        },
        text: {
            label: 'Text', icon: '¶',
            defaults: { html: '<p>Neuer Textabsatz.</p>', variant: 'standard' },
            fields: [
                { key: 'html', label: 'Inhalt', type: 'richtext' },
                variantField('text'),
            ],
        },
        image: {
            label: 'Bild', icon: '▣',
            defaults: { src: '', alt: '', caption: '', link: '', variant: 'standard' },
            fields: [
                { key: 'src', label: 'Bild', type: 'image' },
                { key: 'alt', label: 'Alternativtext', type: 'text' },
                { key: 'caption', label: 'Bildunterschrift', type: 'text' },
                { key: 'link', label: 'Verlinkung (optional)', type: 'link' },
                variantField('image'),
            ],
        },
        gallery: {
            label: 'Bildergalerie', icon: '⊞',
            defaults: { images: [], columns: 3, lightbox: 1, show_captions: 0, variant: 'standard' },
            items: {
                key: 'images', label: 'Bilder', itemLabel: 'Bild',
                fields: [
                    { key: 'src', label: 'Bild', type: 'image' },
                    { key: 'caption', label: 'Bildunterschrift', type: 'text' },
                    { key: 'alt', label: 'Alternativtext', type: 'text' },
                ],
            },
            fields: [
                { key: 'columns', label: 'Spalten', type: 'select', options: [['2', '2'], ['3', '3'], ['4', '4'], ['5', '5'], ['6', '6']] },
                { key: 'lightbox', label: 'Lightbox (Klick vergrößert)', type: 'checkbox' },
                { key: 'show_captions', label: 'Bildunterschriften anzeigen', type: 'checkbox' },
                variantField('gallery'),
            ],
        },
        slider: {
            label: 'Slider', icon: '⇆',
            defaults: { images: [], height: 420, autoplay: 1, interval: 5, arrows: 1, dots: 1 },
            items: {
                key: 'images', label: 'Slides', itemLabel: 'Slide',
                fields: [
                    { key: 'src', label: 'Bild', type: 'image' },
                    { key: 'caption', label: 'Bildunterschrift', type: 'text' },
                ],
            },
            fields: [
                { key: 'height', label: 'Höhe (px)', type: 'number' },
                { key: 'autoplay', label: 'Automatisch wechseln', type: 'checkbox' },
                { key: 'interval', label: 'Wechsel alle … Sekunden', type: 'number' },
                { key: 'arrows', label: 'Pfeile anzeigen', type: 'checkbox' },
                { key: 'dots', label: 'Punkte anzeigen', type: 'checkbox' },
            ],
        },
        hero: {
            label: 'Hero (volle Breite)', icon: '▬',
            defaults: { slides: [], height: 0, overlay: 'medium', autoplay: 1, interval: 6, arrows: 1, dots: 1 },
            items: {
                key: 'slides', label: 'Slides', itemLabel: 'Slide',
                fields: [
                    { key: 'src', label: 'Hintergrundbild', type: 'image' },
                    { key: 'title', label: 'Überschrift', type: 'text' },
                    { key: 'text', label: 'Text', type: 'textarea' },
                    { key: 'button_text', label: 'Button-Beschriftung', type: 'text' },
                    { key: 'button_url', label: 'Button-Ziel', type: 'link' },
                ],
            },
            fields: [
                { key: 'height', label: 'Höhe (% der Bildschirmhöhe, 0 = automatisch nach Design)', type: 'number' },
                { key: 'overlay', label: 'Abdunkelung', type: 'select', options: [['none', 'Keine'], ['light', 'Leicht'], ['medium', 'Mittel'], ['dark', 'Stark']] },
                { key: 'autoplay', label: 'Automatisch wechseln', type: 'checkbox' },
                { key: 'interval', label: 'Wechsel alle … Sekunden', type: 'number' },
                { key: 'arrows', label: 'Pfeile anzeigen', type: 'checkbox' },
                { key: 'dots', label: 'Punkte anzeigen', type: 'checkbox' },
            ],
        },
        button: {
            label: 'Button', icon: '⏺',
            defaults: { text: 'Mehr erfahren', url: '#', style: 'primary', size: 'normal' },
            fields: [
                { key: 'text', label: 'Beschriftung', type: 'text' },
                { key: 'url', label: 'Link-Ziel', type: 'link' },
                { key: 'style', label: 'Designvorlage', type: 'select', options: [['primary', 'Primärfarbe'], ['accent', 'Akzentfarbe'], ['outline', 'Outline'], ['ghost', 'Dezent']] },
                { key: 'size', label: 'Größe', type: 'select', options: [['small', 'Klein'], ['normal', 'Normal'], ['large', 'Groß']] },
            ],
        },
        video: {
            label: 'Video', icon: '▶',
            defaults: { url: '' },
            fields: [{ key: 'url', label: 'YouTube-/Vimeo-Link oder MP4-URL', type: 'text' }],
        },
        quote: {
            label: 'Zitat', icon: '❝',
            defaults: { text: '', author: '', variant: 'standard' },
            fields: [
                { key: 'text', label: 'Zitat', type: 'textarea' },
                { key: 'author', label: 'Von wem?', type: 'text' },
                variantField('quote'),
            ],
        },
        accordion: {
            label: 'Akkordeon', icon: '≡',
            defaults: { items: [], first_open: 1, variant: 'standard' },
            items: {
                key: 'items', label: 'Abschnitte', itemLabel: 'Abschnitt',
                fields: [
                    { key: 'title', label: 'Titel', type: 'text' },
                    { key: 'text', label: 'Inhalt (HTML erlaubt)', type: 'textarea' },
                ],
            },
            fields: [
                { key: 'first_open', label: 'Ersten Abschnitt geöffnet zeigen', type: 'checkbox' },
                variantField('accordion'),
            ],
        },
        news: {
            label: 'News', icon: '❑',
            defaults: { count: 3, columns: 3, layout: 'cards', show_image: 1, show_date: 1, show_excerpt: 1 },
            fields: [
                { key: 'count', label: 'Anzahl Beiträge', type: 'number' },
                { key: 'layout', label: 'Designvorlage', type: 'select', options: [['cards', 'Karten'], ['list', 'Liste'], ['minimal', 'Minimal (nur Titel)']] },
                { key: 'columns', label: 'Spalten (bei Karten)', type: 'select', options: [['1', '1'], ['2', '2'], ['3', '3'], ['4', '4']] },
                { key: 'show_image', label: 'Bild anzeigen', type: 'checkbox' },
                { key: 'show_date', label: 'Datum anzeigen', type: 'checkbox' },
                { key: 'show_excerpt', label: 'Kurzbeschreibung anzeigen', type: 'checkbox' },
            ],
        },
        events: {
            label: 'Events', icon: '◷',
            defaults: { count: 3, columns: 3, layout: 'cards', show_image: 1, show_date: 1, show_excerpt: 1, show_location: 1 },
            fields: [
                { key: 'count', label: 'Anzahl Termine', type: 'number' },
                { key: 'layout', label: 'Designvorlage', type: 'select', options: [['cards', 'Karten'], ['list', 'Liste'], ['minimal', 'Minimal (nur Titel)']] },
                { key: 'columns', label: 'Spalten (bei Karten)', type: 'select', options: [['1', '1'], ['2', '2'], ['3', '3'], ['4', '4']] },
                { key: 'show_image', label: 'Bild anzeigen', type: 'checkbox' },
                { key: 'show_date', label: 'Termin anzeigen', type: 'checkbox' },
                { key: 'show_location', label: 'Ort anzeigen', type: 'checkbox' },
                { key: 'show_excerpt', label: 'Kurzbeschreibung anzeigen', type: 'checkbox' },
            ],
        },
        form: {
            label: 'Kontaktformular', icon: '✉',
            defaults: { recipient: '', subject: 'Neue Nachricht über das Kontaktformular', button_text: 'Nachricht senden', success: 'Vielen Dank! Deine Nachricht wurde gesendet.', show_name: 1, show_phone: 0, fields: [] },
            items: {
                key: 'fields', label: 'Eigene Felder (optional)', itemLabel: 'Feld',
                fields: [
                    { key: 'label', label: 'Beschriftung', type: 'text' },
                    { key: 'type', label: 'Feldtyp', type: 'select', options: [['text', 'Textzeile'], ['textarea', 'Textbereich'], ['select', 'Auswahlliste'], ['checkbox', 'Checkbox']] },
                    { key: 'options', label: 'Optionen (bei Auswahlliste, mit Komma trennen)', type: 'text' },
                    { key: 'required', label: 'Pflichtfeld', type: 'checkbox' },
                ],
            },
            fields: [
                { key: 'recipient', label: 'Empfänger-E-Mail (leer = aus den Einstellungen)', type: 'text' },
                { key: 'subject', label: 'E-Mail-Betreff', type: 'text' },
                { key: 'button_text', label: 'Button-Beschriftung', type: 'text' },
                { key: 'success', label: 'Erfolgsmeldung', type: 'textarea' },
                { key: 'show_name', label: 'Namensfeld anzeigen', type: 'checkbox' },
                { key: 'show_phone', label: 'Telefonfeld anzeigen', type: 'checkbox' },
            ],
        },
        search: {
            label: 'Suchfeld', icon: '🔍',
            defaults: { placeholder: 'Suchbegriff …', button_text: 'Suchen' },
            fields: [
                { key: 'placeholder', label: 'Platzhalter-Text', type: 'text' },
                { key: 'button_text', label: 'Button-Beschriftung', type: 'text' },
            ],
        },
        global: {
            label: 'Globaler Block', icon: '∞',
            defaults: { page_id: '' },
            fields: [
                { key: 'page_id', label: 'Globaler Block (unter "Globale Blöcke" pflegen)', type: 'select', options: (window.CMS_GLOBAL_BLOCKS && window.CMS_GLOBAL_BLOCKS.length ? window.CMS_GLOBAL_BLOCKS : [['', '– Noch keine globalen Blöcke angelegt –']]) },
            ],
        },
        map: {
            label: 'Karte (OSM)', icon: '🗺',
            defaults: { lat: 51.1634, lon: 10.4477, zoom: 14, height: 380 },
            fields: [
                { key: 'lat', label: 'Breitengrad (z. B. 48.1372)', type: 'text' },
                { key: 'lon', label: 'Längengrad (z. B. 11.5756)', type: 'text' },
                { key: 'zoom', label: 'Zoom (2–19)', type: 'number' },
                { key: 'height', label: 'Höhe (px)', type: 'number' },
            ],
        },
        team: {
            label: 'Team', icon: '☺',
            defaults: { members: [], columns: 3, zoom: 0 },
            items: {
                key: 'members', label: 'Team-Mitglieder', itemLabel: 'Person',
                fields: [
                    { key: 'src', label: 'Foto', type: 'image' },
                    { key: 'name', label: 'Name', type: 'text' },
                    { key: 'role', label: 'Position', type: 'text' },
                    { key: 'email', label: 'E-Mail', type: 'text' },
                    { key: 'phone', label: 'Telefon', type: 'text' },
                    { key: 'text', label: 'Kurzbeschreibung', type: 'textarea' },
                ],
            },
            fields: [
                { key: 'columns', label: 'Spalten', type: 'select', options: [['2', '2'], ['3', '3'], ['4', '4']] },
                { key: 'zoom', label: 'Fotos per Klick vergrößern (Lightbox)', type: 'checkbox' },
            ],
        },
        pricing: {
            label: 'Preistabelle', icon: '€',
            defaults: { plans: [] },
            items: {
                key: 'plans', label: 'Tarife', itemLabel: 'Tarif',
                fields: [
                    { key: 'title', label: 'Name (z. B. Basis)', type: 'text' },
                    { key: 'price', label: 'Preis (z. B. 19 €)', type: 'text' },
                    { key: 'period', label: 'Zeitraum (z. B. Monat)', type: 'text' },
                    { key: 'features', label: 'Leistungen (eine pro Zeile)', type: 'textarea' },
                    { key: 'button_text', label: 'Button-Beschriftung', type: 'text' },
                    { key: 'button_url', label: 'Button-Ziel', type: 'link' },
                    { key: 'highlight', label: 'Hervorheben (empfohlen)', type: 'checkbox' },
                ],
            },
            fields: [],
        },
        countdown: {
            label: 'Countdown', icon: '⏳',
            defaults: { target: '', title: '', expired_text: 'Es ist so weit!' },
            fields: [
                { key: 'target', label: 'Zieldatum (z. B. 2026-12-31 18:00)', type: 'text' },
                { key: 'title', label: 'Überschrift (optional)', type: 'text' },
                { key: 'expired_text', label: 'Text nach Ablauf', type: 'text' },
            ],
        },
        social: {
            label: 'Social Media', icon: '♥',
            defaults: { links: [], size: 'normal' },
            items: {
                key: 'links', label: 'Profile', itemLabel: 'Profil',
                fields: [
                    { key: 'network', label: 'Netzwerk', type: 'select', options: [['facebook', 'Facebook'], ['instagram', 'Instagram'], ['x', 'X (Twitter)'], ['youtube', 'YouTube'], ['linkedin', 'LinkedIn'], ['tiktok', 'TikTok'], ['whatsapp', 'WhatsApp'], ['mail', 'E-Mail'], ['phone', 'Telefon']] },
                    { key: 'url', label: 'Link (bei E-Mail: mailto:…, Telefon: tel:…)', type: 'text' },
                ],
            },
            fields: [
                { key: 'size', label: 'Größe', type: 'select', options: [['small', 'Klein'], ['normal', 'Normal'], ['large', 'Groß']] },
            ],
        },
        html: {
            label: 'HTML', icon: '</>',
            defaults: { code: '<!-- Eigener HTML-Code -->' },
            fields: [{ key: 'code', label: 'HTML-Code', type: 'textarea' }],
        },
        divider: { label: 'Trennlinie', icon: '—', defaults: {}, fields: [] },
        spacer: {
            label: 'Abstand', icon: '↕',
            defaults: { height: 40 },
            fields: [{ key: 'height', label: 'Höhe (px)', type: 'number' }],
        },
    };

    // Grafische Einstellungen ohne CSS – für jeden Block verfügbar.
    const STYLE_FIELDS = [
        { key: 'mt', label: 'Abstand oben (px)', type: 'number' },
        { key: 'mb', label: 'Abstand unten (px)', type: 'number' },
        { key: 'ml', label: 'Abstand links (px)', type: 'number' },
        { key: 'mr', label: 'Abstand rechts (px)', type: 'number' },
        { key: 'p', label: 'Innenabstand (px)', type: 'number' },
        { key: 'disp', label: 'Darstellung', type: 'select', options: [['', 'Standard'], ['inline', 'Nebeneinander (inline-block)'], ['block', 'Untereinander (Block, volle Zeile)']] },
        { key: 'align', label: 'Ausrichtung', type: 'select', options: [['', 'Standard'], ['left', 'Links'], ['center', 'Zentriert'], ['right', 'Rechts']] },
        { key: 'color', label: 'Textfarbe', type: 'colorclear' },
        { key: 'bg', label: 'Hintergrundfarbe', type: 'colorclear' },
        { key: 'radius', label: 'Eckenrundung (px)', type: 'number' },
        { key: 'anim', label: 'Beim Scrollen einblenden', type: 'select', options: [
            ['', 'Keine Animation'],
            ['fade', 'Einblenden (Fade)'],
            ['up', 'Von unten einfahren'],
            ['left', 'Von links einfahren'],
            ['right', 'Von rechts einfahren'],
            ['zoom', 'Sanft vergrößern'],
        ] },
    ];

    if (editorMode === 'layout') {
        blockDefs = Object.assign({}, layoutDefs, blockDefs);
    }

    const presets = [
        { label: '1', spans: [12], title: '1 Spalte' },
        { label: '½ ½', spans: [6, 6], title: '2 gleiche Spalten' },
        { label: '⅓ ⅓ ⅓', spans: [4, 4, 4], title: '3 gleiche Spalten' },
        { label: '¼ ×4', spans: [3, 3, 3, 3], title: '4 gleiche Spalten' },
        { label: '⅔ ⅓', spans: [8, 4], title: 'Breit / Schmal' },
        { label: '⅓ ⅔', spans: [4, 8], title: 'Schmal / Breit' },
        { label: '¼ ½ ¼', spans: [3, 6, 3], title: 'Rand / Mitte / Rand' },
    ];

    /* ---------- Fertige Sektionen & Block-Kategorien ---------- */

    function sBlock(type, data) {
        const block = newBlock(type);
        Object.assign(block.data, data || {});
        return block;
    }

    function sRow(spans, blocksPerCol) {
        return {
            id: uid('row'),
            columns: spans.map((span, i) => ({ id: uid('col'), span: span, blocks: blocksPerCol[i] || [] })),
        };
    }

    const SECTIONS = [
        { title: 'Hero mit Button', icon: '▬', desc: 'Große Bühne mit Titel, Text und Button', build: () => sRow([12], [[
            sBlock('hero', { slides: [{ src: '', title: 'Willkommen!', text: 'Hier beginnt deine Geschichte.', button_text: 'Mehr erfahren', button_url: '#' }], height: 55 }),
        ]]) },
        { title: 'Text + Bild', icon: '◧', desc: 'Zweispaltig: Text links, Bild rechts', build: () => sRow([6, 6], [[
            sBlock('heading', { text: 'Über uns', level: 'h2' }),
            sBlock('text', { html: '<p>Erzähle hier, was dich ausmacht – zwei bis drei Sätze reichen für den Anfang.</p>' }),
        ], [
            sBlock('image', {}),
        ]]) },
        { title: '3 Karten', icon: '⫴', desc: 'Drei Spalten mit Titel und Text', build: () => sRow([4, 4, 4], [1, 2, 3].map((n) => [
            sBlock('heading', { text: 'Vorteil ' + n, level: 'h3' }),
            sBlock('text', { html: '<p>Beschreibe hier kurz deinen ' + n + '. Vorteil.</p>', variant: 'infobox' }),
        ])) },
        { title: 'Zitat', icon: '❝', desc: 'Großes zentriertes Zitat', build: () => sRow([12], [[
            sBlock('quote', { text: 'Das Beste, was wir je gemacht haben!', author: 'Eine zufriedene Kundin', variant: 'big' }),
        ]]) },
        { title: 'Preistabelle', icon: '€', desc: 'Drei Tarife zum Anpassen', build: () => sRow([12], [[
            sBlock('pricing', { plans: [
                { title: 'Basis', price: '9 €', period: 'Monat', features: 'Alle Grundfunktionen\nE-Mail-Support', button_text: 'Auswählen', button_url: '#', highlight: 0 },
                { title: 'Pro', price: '19 €', period: 'Monat', features: 'Alles aus Basis\nSchneller Support\nAlle Extras', button_text: 'Auswählen', button_url: '#', highlight: 1 },
                { title: 'Business', price: '49 €', period: 'Monat', features: 'Alles aus Pro\nPersönliche Beratung', button_text: 'Anfragen', button_url: '#', highlight: 0 },
            ] }),
        ]]) },
        { title: 'Kontakt', icon: '✉', desc: 'Überschrift, Text und Kontaktformular', build: () => sRow([5, 7], [[
            sBlock('heading', { text: 'Kontakt', level: 'h2' }),
            sBlock('text', { html: '<p>Schreib uns – wir melden uns schnellstmöglich zurück.</p>' }),
        ], [
            sBlock('form', {}),
        ]]) },
    ];

    function blockCategories() {
        const cats = [];
        if (editorMode === 'layout') {
            cats.push(['Layout', ['l-brand', 'l-menu', 'l-content', 'l-languages']]);
        }
        cats.push(
            ['Text', ['heading', 'text', 'quote', 'accordion', 'html']],
            ['Medien', ['image', 'gallery', 'slider', 'hero', 'video']],
            ['Elemente', ['button', 'divider', 'spacer', 'social', 'countdown', 'map', 'search']],
            ['Inhalte & Daten', ['news', 'events', 'team', 'pricing', 'form', 'global']]
        );
        return cats;
    }

    const canvas = root.querySelector('.ed-canvas');
    const paletteWrap = root.querySelector('.ed-palette-items');
    const inspectorBody = root.querySelector('.ed-inspector-body');
    const presetBar = root.querySelector('.ed-presets');
    const saveBtn = document.getElementById('ed-save');
    const statusEl = document.getElementById('ed-status');
    const cssBtn = document.getElementById('ed-css-btn');
    const cssPanel = root.querySelector('.ed-css-panel');
    const cssInput = document.getElementById('ed-css-input');
    const pageCssTag = document.getElementById('ed-page-css');

    let selected = null;   // {kind:'block', r,c,b} | {kind:'row', r}
    let dragData = null;   // {kind:'new',type} | {kind:'move',from} | {kind:'row',from}
    let dirty = false;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[ch]);

    /* ---------- Rückgängig / Wiederholen ---------- */

    let history = [];
    let future = [];
    let lastPush = 0;
    let lastSnapshot = JSON.stringify(state);
    let snapTimer = null;

    // Vor jeder Änderung wird der vorherige Stand gesichert; schnelle
    // Folgeänderungen (Tippen) werden zu EINEM Undo-Schritt zusammengefasst.
    function pushHistory() {
        const now = Date.now();
        if (now - lastPush > 600) {
            history.push(lastSnapshot);
            if (history.length > 60) history.shift();
            future = [];
        }
        lastPush = now;
        clearTimeout(snapTimer);
        snapTimer = setTimeout(() => { lastSnapshot = JSON.stringify(state); }, 0);
    }

    function restoreState(json) {
        state = JSON.parse(json);
        if (typeof state.css !== 'string') state.css = '';
        lastSnapshot = json;
        lastPush = 0;
        cssInput.value = state.css;
        pageCssTag.textContent = state.css;
        dirty = true;
        statusEl.textContent = 'Ungespeicherte Änderungen';
        statusEl.className = 'ed-status is-dirty';
        deselect();
        render();
    }

    function undo() {
        if (!history.length) return;
        clearTimeout(snapTimer);
        future.push(JSON.stringify(state));
        restoreState(history.pop());
    }

    function redo() {
        if (!future.length) return;
        clearTimeout(snapTimer);
        history.push(JSON.stringify(state));
        restoreState(future.pop());
    }

    function markDirty() {
        pushHistory();
        dirty = true;
        statusEl.textContent = 'Ungespeicherte Änderungen';
        statusEl.className = 'ed-status is-dirty';
    }

    function newBlock(type) {
        return { id: uid('b'), type: type, data: JSON.parse(JSON.stringify(blockDefs[type].defaults)) };
    }

    function countBlocksOfType(type) {
        let count = 0;
        state.rows.forEach((row) => row.columns.forEach((col) =>
            col.blocks.forEach((block) => { if (block.type === type) count++; })));
        return count;
    }

    /* ---------- Serverseitige WYSIWYG-Vorschau ---------- */

    const previewCache = new Map();
    const keyOf = (block) => JSON.stringify([block.type, block.data]);
    let previewQueue = new Map(); // key -> { block, els: [] }
    let flushScheduled = false;

    const EMPTY_HTML = '<div class="ed-empty-block">Leerer Block – Eigenschaften rechts ausfüllen</div>';

    function setPreviewHtml(el, html) {
        // Nicht unter dem Cursor wegrendern, während direkt im Block getippt wird.
        if (el.contains(document.activeElement) && document.activeElement.isContentEditable) return;
        el.innerHTML = html !== '' ? html : EMPTY_HTML;
    }

    function queuePreview(block, el) {
        const key = keyOf(block);
        if (previewCache.has(key)) {
            setPreviewHtml(el, previewCache.get(key));
            return;
        }
        if (!previewQueue.has(key)) {
            previewQueue.set(key, { block: { type: block.type, data: block.data }, els: [] });
        }
        previewQueue.get(key).els.push(el);
        if (!flushScheduled) {
            flushScheduled = true;
            setTimeout(flushPreviews, 10);
        }
    }

    async function flushPreviews() {
        flushScheduled = false;
        if (!previewQueue.size) return;
        const entries = Array.from(previewQueue.entries());
        previewQueue = new Map();
        try {
            const res = await fetch(previewUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ blocks: entries.map(([, entry]) => entry.block) }),
            });
            const json = await res.json();
            entries.forEach(([key, entry], i) => {
                const html = (json.html && json.html[i]) || '';
                previewCache.set(key, html);
                if (previewCache.size > 400) previewCache.delete(previewCache.keys().next().value);
                entry.els.forEach((el) => { if (el.isConnected) setPreviewHtml(el, html); });
            });
        } catch (err) {
            entries.forEach(([, entry]) => entry.els.forEach((el) => {
                if (el.isConnected) el.innerHTML = '<div class="ed-empty-block">Vorschau nicht verfügbar</div>';
            }));
        }
    }

    let liveTimer = null;
    function scheduleLivePreview() {
        clearTimeout(liveTimer);
        liveTimer = setTimeout(() => {
            const block = selectedBlock();
            const el = canvas.querySelector('.ed-block.is-selected .ed-block-preview');
            if (block && el) queuePreview(block, el);
            if (selected && selected.kind === 'row') applyRowStyles();
        }, 300);
    }

    /* ---------- Rendern ---------- */

    function makeRowInsert(at) {
        const ins = document.createElement('div');
        ins.className = 'ed-row-insert';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = '+ Zeile';
        btn.title = 'Zeile hier einfügen';
        btn.addEventListener('click', () => openRowPicker(at));
        ins.appendChild(btn);
        return ins;
    }

    function render() {
        canvas.innerHTML = '';

        if (!state.rows.length) {
            const hint = document.createElement('div');
            hint.className = 'ed-empty';
            hint.innerHTML = '<p><strong>Noch keine Inhalte.</strong><br>Starte mit einer fertigen Sektion oder einer leeren Zeile.</p>';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-primary';
            btn.textContent = '+ Erste Zeile hinzufügen';
            btn.addEventListener('click', () => openRowPicker(0));
            hint.appendChild(btn);
            canvas.appendChild(hint);
            return;
        }

        state.rows.forEach((row, r) => {
            canvas.appendChild(makeRowInsert(r));
            const rowEl = document.createElement('div');
            rowEl.className = 'ed-row';
            // Mobiles Spaltenverhalten in der Geräte-Vorschau spiegeln:
            // data-bp="0" = bleibt nebeneinander, sonst stapelt die Vorschau.
            const rowBp = row.style && row.style.bp;
            if (rowBp === 0 || rowBp === '0' || Number(rowBp) > 0) {
                rowEl.setAttribute('data-bp', String(Number(rowBp) || 0));
            }
            if (selected && selected.kind === 'row' && selected.r === r) rowEl.classList.add('is-selected');
            bindRowDropzone(rowEl, r);

            // Klick irgendwo in die Zeile (nicht auf einen Block, Knopf oder
            // Eingabefeld) markiert die Zeile und öffnet ihre Einstellungen.
            rowEl.addEventListener('click', (e) => {
                if (e.target.closest('.ed-block, button, input, select, textarea, a, [contenteditable="true"], .ed-add-block, .ed-col-resizer')) {
                    return;
                }
                // Klicks INNERHALB einer Spalte (z. B. neben einem schmalen
                // Block wie einem Button) wählen NICHT die Zeile – sonst ließe
                // sich der Block nicht mehr treffen. Die Zeile wird über ihre
                // Leiste („⠿ Zeile") oder den farbigen Bereich außerhalb der
                // Spalten ausgewählt.
                if (e.target.closest('.ed-col')) {
                    return;
                }
                selectRow(r);
            });

            const bar = document.createElement('div');
            bar.className = 'ed-row-bar';
            bar.innerHTML =
                '<span class="ed-row-label" draggable="true" title="Klicken für Zeilen-Einstellungen, Ziehen zum Verschieben">⠿ Zeile ' + (r + 1) + '</span>' +
                '<span class="ed-row-tools">' +
                '<button type="button" data-act="row-up" title="Nach oben">↑</button>' +
                '<button type="button" data-act="row-down" title="Nach unten">↓</button>' +
                '<button type="button" data-act="col-add" title="Spalte hinzufügen">+ Spalte</button>' +
                '<button type="button" data-act="row-dup" title="Zeile duplizieren (Strg+D)">⧉</button>' +
                '<button type="button" data-act="row-del" class="danger" title="Zeile löschen">✕</button>' +
                '</span>';
            bar.addEventListener('click', (e) => {
                const act = e.target.dataset && e.target.dataset.act;
                if (act) { rowAction(act, r); return; }
                if (e.target.closest('.ed-row-label')) selectRow(r);
            });

            const handle = bar.querySelector('.ed-row-label');
            handle.addEventListener('dragstart', (e) => {
                dragData = { kind: 'row', from: r };
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', 'row');
                setTimeout(() => rowEl.classList.add('is-dragging'), 0);
            });
            handle.addEventListener('dragend', () => {
                dragData = null;
                rowEl.classList.remove('is-dragging');
                clearDropHints();
            });

            rowEl.appendChild(bar);

            const colsEl = document.createElement('div');
            colsEl.className = 'ed-cols';

            row.columns.forEach((col, c) => {
                const colEl = document.createElement('div');
                colEl.className = 'ed-col';
                colEl.style.setProperty('--span', col.span);

                const colBar = document.createElement('div');
                colBar.className = 'ed-col-bar';
                colBar.title = 'Spalte anklicken für Gestaltung (Hintergrund, Breite …)';
                colBar.innerHTML =
                    '<button type="button" data-act="narrower" title="Schmaler">−</button>' +
                    '<span class="ed-col-width">' + col.span + '/12</span>' +
                    '<button type="button" data-act="wider" title="Breiter">+</button>' +
                    '<button type="button" data-act="col-cfg" title="Spalte gestalten">⚙</button>' +
                    '<button type="button" data-act="col-del" class="danger" title="Spalte löschen">✕</button>';
                colBar.addEventListener('click', (e) => {
                    const act = e.target.dataset && e.target.dataset.act;
                    if (act === 'col-cfg') { selectCol(r, c); return; }
                    if (act) { colAction(act, r, c); return; }
                    // Klick auf die Leiste selbst (nicht auf einen Knopf) wählt die Spalte.
                    selectCol(r, c);
                });
                colEl.appendChild(colBar);

                const blocksEl = document.createElement('div');
                blocksEl.className = 'ed-blocks';
                bindDropzone(blocksEl, r, c);

                // Klick in den freien Bereich einer Spalte wählt den nächst-
                // gelegenen Block (macht schmale Blöcke wie Buttons leicht
                // treffbar). Klicks direkt auf einem Block laufen über dessen
                // eigenen Handler (stopPropagation).
                blocksEl.addEventListener('click', (e) => {
                    if (e.target.closest('.ed-block, .ed-add-block, button, input, select, textarea, a, [contenteditable="true"]')) {
                        return;
                    }
                    const blocks = state.rows[r].columns[c].blocks;
                    if (!blocks.length) return;
                    const els = Array.from(blocksEl.querySelectorAll(':scope > .ed-block'));
                    let bestIdx = 0;
                    let bestDist = Infinity;
                    els.forEach((el, i) => {
                        const rect = el.getBoundingClientRect();
                        const dist = Math.abs((rect.top + rect.height / 2) - e.clientY);
                        if (dist < bestDist) { bestDist = dist; bestIdx = i; }
                    });
                    selectBlock(r, c, bestIdx);
                });

                col.blocks.forEach((block, b) => {
                    blocksEl.appendChild(renderBlock(block, r, c, b));
                });

                if (!col.blocks.length) {
                    const empty = document.createElement('div');
                    empty.className = 'ed-col-empty';
                    empty.textContent = 'Noch leer – unten „+ Block“ klicken oder Block hierher ziehen';
                    blocksEl.appendChild(empty);
                }

                const addBtn = document.createElement('button');
                addBtn.type = 'button';
                addBtn.className = 'ed-add-block';
                addBtn.textContent = '+ Block';
                addBtn.title = 'Block in dieser Spalte hinzufügen';
                addBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    openBlockPicker((type) => insertNewBlock(r, c, state.rows[r].columns[c].blocks.length, type));
                });
                blocksEl.appendChild(addBtn);

                // Spaltenbreite durch Ziehen der rechten Kante ändern
                const resizer = document.createElement('div');
                resizer.className = 'ed-col-resizer';
                resizer.title = 'Ziehen, um die Spaltenbreite zu ändern';
                bindColResizer(resizer, colEl, colBar, r, c);
                colEl.appendChild(resizer);

                colEl.appendChild(blocksEl);
                if (selected && selected.kind === 'col' && selected.r === r && selected.c === c) {
                    colEl.classList.add('is-selected');
                }
                applyColStyleTo(blocksEl, col);
                colsEl.appendChild(colEl);
            });

            rowEl.appendChild(colsEl);
            applyRowStyleTo(rowEl, row);
            canvas.appendChild(rowEl);
        });

        canvas.appendChild(makeRowInsert(state.rows.length));
    }

    // Hintergrundfarben aus der Layout-Palette (Gestaltung) für Zeilen.
    const ROW_BG_PALETTE = {
        primary: 'var(--cms-primary)',
        accent: 'var(--cms-accent)',
        surface: 'var(--cms-surface)',
        page: 'var(--cms-bg)',
    };

    function applyRowStyleTo(rowEl, row) {
        const style = row.style || {};
        const colsEl = rowEl.querySelector('.ed-cols');
        const bg = style.bg || '';
        colsEl.style.background = /^#/.test(bg) ? bg : (ROW_BG_PALETTE[bg] || '');
        colsEl.style.paddingTop = (12 + (parseInt(style.pt, 10) || 0)) + 'px';
        colsEl.style.paddingBottom = (12 + (parseInt(style.pb, 10) || 0)) + 'px';
        rowEl.classList.toggle('ed-row-fullwidth', style.width === 'full');
        // Mobiles Spaltenverhalten (für die Geräte-Vorschau).
        if (style.bp === 0 || style.bp === '0' || Number(style.bp) > 0) {
            rowEl.setAttribute('data-bp', String(Number(style.bp) || 0));
        } else {
            rowEl.removeAttribute('data-bp');
        }
    }

    function applyRowStyles() {
        if (!selected || selected.kind !== 'row') return;
        const rowEl = canvas.querySelectorAll('.ed-row')[selected.r];
        if (rowEl) applyRowStyleTo(rowEl, state.rows[selected.r]);
    }

    // Spalten-Gestaltung in der Editor-Vorschau spiegeln (Hintergrund,
    // Innenabstand, Ausrichtung, Ecken). Vertikale Ausrichtung wirkt nur im
    // Frontend (dort haben Spalten einer Zeile gleiche Höhe) und wird hier
    // ausgelassen.
    function applyColStyleTo(blocksEl, col) {
        const style = (col && col.style) || {};
        const bg = style.bg || '';
        blocksEl.style.background = /^#/.test(bg) ? bg : (ROW_BG_PALETTE[bg] || '');
        blocksEl.style.padding = (style.p != null && style.p !== '') ? ((parseInt(style.p, 10) || 0) + 'px') : '';
        blocksEl.style.textAlign = ['left', 'center', 'right'].includes(style.align) ? style.align : '';
        blocksEl.style.borderRadius = (style.radius != null && style.radius !== '') ? ((parseInt(style.radius, 10) || 0) + 'px') : '';
    }

    function applyColStyles() {
        if (!selected || selected.kind !== 'col') return;
        const rowEl = canvas.querySelectorAll('.ed-row')[selected.r];
        const colEl = rowEl && rowEl.querySelectorAll(':scope > .ed-cols > .ed-col')[selected.c];
        const blocksEl = colEl && colEl.querySelector(':scope > .ed-blocks');
        if (blocksEl) applyColStyleTo(blocksEl, state.rows[selected.r].columns[selected.c]);
    }

    // Steht der Block nebeneinander (inline-block)? Buttons standardmäßig ja
    // (wie im Frontend), sonst nur wenn „Darstellung" auf inline steht.
    function isInlineBlock(block) {
        const d = block && block.data && block.data._style && block.data._style.disp;
        if (d === 'inline') return true;
        if (d === 'block') return false;
        return block && block.type === 'button';
    }

    function renderBlock(block, r, c, b) {
        const def = blockDefs[block.type] || { label: block.type, icon: '?' };
        const el = document.createElement('div');
        el.className = 'ed-block' + (isInlineBlock(block) ? ' ed-block-inline' : '');
        el.draggable = true;
        if (selected && selected.kind === 'block' && selected.r === r && selected.c === c && selected.b === b) {
            el.classList.add('is-selected');
        }
        const head = document.createElement('div');
        head.className = 'ed-block-head';
        head.innerHTML = '<span class="ed-block-icon">' + def.icon + '</span>' + esc(def.label) +
            '<span class="ed-block-tools">' +
            '<button type="button" data-bact="up" title="Nach oben">↑</button>' +
            '<button type="button" data-bact="down" title="Nach unten">↓</button>' +
            '<button type="button" data-bact="dup" title="Duplizieren (Strg+D)">⧉</button>' +
            '<button type="button" data-bact="del" class="danger" title="Löschen (Entf)">✕</button>' +
            '</span>';
        head.addEventListener('click', (e) => {
            const act = e.target.dataset && e.target.dataset.bact;
            if (!act) return;
            e.preventDefault();
            e.stopPropagation();
            if (act === 'up') moveBlock(r, c, b, -1);
            else if (act === 'down') moveBlock(r, c, b, 1);
            else if (act === 'dup') duplicateBlock(r, c, b);
            else if (act === 'del') deleteBlock(r, c, b);
        });
        const preview = document.createElement('div');
        preview.className = 'ed-block-preview';
        preview.innerHTML = '<div class="ed-loading">⋯</div>';
        el.appendChild(head);
        el.appendChild(preview);
        queuePreview(block, preview);

        el.addEventListener('click', (e) => {
            // In bearbeitbarem Text nicht die Standard-Aktion unterdrücken,
            // sonst lässt sich der Cursor nicht platzieren.
            if (!e.target.isContentEditable) e.preventDefault();
            e.stopPropagation();
            selectBlock(r, c, b);
        });
        el.addEventListener('dragstart', (e) => {
            dragData = { kind: 'move', from: { r: r, c: c, b: b } };
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', 'move');
            e.stopPropagation();
            setTimeout(() => el.classList.add('is-dragging'), 0);
        });
        el.addEventListener('dragend', () => {
            dragData = null;
            el.classList.remove('is-dragging');
            clearDropHints();
        });
        return el;
    }

    /* ---------- Auswahl & Inspektor ---------- */

    // Überschriften und Texte direkt im Canvas beschreibbar machen.
    const INLINE_TYPES = {
        heading: { sel: 'h1, h2, h3, h4, h5, h6', key: 'text', mode: 'text' },
        text: { sel: '.cms-text', key: 'html', mode: 'html' },
    };

    function enableInlineEdit(blockEl, block) {
        const spec = INLINE_TYPES[block.type];
        if (!spec) return;
        const target = blockEl.querySelector('.ed-block-preview ' + spec.sel.split(',').map((s) => s.trim()).join(', .ed-block-preview '));
        if (!target || target.isContentEditable) return;
        target.contentEditable = 'true';
        target.classList.add('ed-inline');
        target.addEventListener('focus', () => { blockEl.draggable = false; });
        target.addEventListener('blur', () => {
            blockEl.draggable = true;
            if (selectedBlock() === block) buildInspector();
        });
        target.addEventListener('input', () => {
            block.data[spec.key] = spec.mode === 'text' ? target.textContent : target.innerHTML;
            markDirty();
        });
    }

    function selectBlock(r, c, b) {
        selected = { kind: 'block', r: r, c: c, b: b };
        refreshSelection();
        buildInspector();
        const blockEl = canvas.querySelector('.ed-block.is-selected');
        const block = selectedBlock();
        if (blockEl && block) enableInlineEdit(blockEl, block);
        openInspector();
    }

    function moveBlock(r, c, b, dir) {
        const blocks = state.rows[r].columns[c].blocks;
        const to = b + dir;
        if (to < 0 || to >= blocks.length) return;
        [blocks[b], blocks[to]] = [blocks[to], blocks[b]];
        markDirty();
        render();
        selectBlock(r, c, to);
    }

    function duplicateBlock(r, c, b) {
        const blocks = state.rows[r].columns[c].blocks;
        if (blocks[b].type === 'l-content') {
            window.AdminDialog.alert('Der Inhaltsbereich kann nur einmal im Layout vorkommen.');
            return;
        }
        const copy = JSON.parse(JSON.stringify(blocks[b]));
        copy.id = uid('b');
        blocks.splice(b + 1, 0, copy);
        markDirty();
        render();
        selectBlock(r, c, b + 1);
    }

    function deleteBlock(r, c, b) {
        state.rows[r].columns[c].blocks.splice(b, 1);
        deselect();
        markDirty();
        render();
    }

    function insertNewBlock(r, c, index, type) {
        if (type === 'l-content' && countBlocksOfType('l-content') > 0) {
            window.AdminDialog.alert('Der Inhaltsbereich ist bereits im Layout vorhanden – er kann nur einmal eingesetzt werden.');
            return;
        }
        state.rows[r].columns[c].blocks.splice(index, 0, newBlock(type));
        markDirty();
        render();
        selectBlock(r, c, index);
    }

    function selectRow(r) {
        selected = { kind: 'row', r: r };
        refreshSelection();
        buildInspector();
        openInspector();
    }

    function selectCol(r, c) {
        selected = { kind: 'col', r: r, c: c };
        refreshSelection();
        buildInspector();
        openInspector();
    }

    function refreshSelection() {
        canvas.querySelectorAll('.ed-block.is-selected, .ed-row.is-selected, .ed-col.is-selected').forEach((el) => el.classList.remove('is-selected'));
        if (!selected) return;
        const rowEl = canvas.querySelectorAll('.ed-row')[selected.r];
        if (!rowEl) return;
        if (selected.kind === 'row') {
            rowEl.classList.add('is-selected');
        } else if (selected.kind === 'col') {
            const colEl = rowEl.querySelectorAll(':scope > .ed-cols > .ed-col')[selected.c];
            if (colEl) colEl.classList.add('is-selected');
        } else {
            const colEl = rowEl.querySelectorAll(':scope > .ed-cols > .ed-col')[selected.c];
            const blockEl = colEl && colEl.querySelectorAll(':scope > .ed-blocks > .ed-block')[selected.b];
            if (blockEl) blockEl.classList.add('is-selected');
        }
    }

    function deselect() {
        selected = null;
        refreshSelection();
        inspectorBody.innerHTML = '<p class="muted small">Klicke auf einen Block oder eine Zeilen-Leiste, um Einstellungen zu sehen.</p>';
        closeInspector();
    }

    function selectedBlock() {
        if (!selected || selected.kind !== 'block') return null;
        const row = state.rows[selected.r];
        const col = row && row.columns[selected.c];
        return (col && col.blocks[selected.b]) || null;
    }

    function fieldInput(field, value, onChange) {
        let input;
        if (field.type === 'textarea') {
            input = document.createElement('textarea');
            input.rows = 6;
            input.className = 'code';
            input.value = value != null ? value : '';
        } else if (field.type === 'richtext') {
            const ta = document.createElement('textarea');
            ta.value = value != null ? value : '';
            ta.addEventListener('input', () => onChange(ta.value));
            const holder = document.createElement('div');
            holder.appendChild(ta);
            if (window.AdminTools) window.AdminTools.richtext(ta);
            return holder;
        } else if (field.type === 'select') {
            input = document.createElement('select');
            (field.options || []).forEach(([v, text]) => {
                const opt = document.createElement('option');
                opt.value = v;
                opt.textContent = text;
                input.appendChild(opt);
            });
            input.value = value != null ? String(value) : '';
        } else if (field.type === 'checkbox') {
            const label = document.createElement('label');
            label.className = 'ed-check';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = !!parseInt(value, 10);
            cb.addEventListener('change', () => onChange(cb.checked ? 1 : 0));
            label.appendChild(cb);
            label.appendChild(document.createTextNode(' ' + field.label));
            return label;
        } else if (field.type === 'colorclear') {
            const wrap = document.createElement('div');
            wrap.className = 'ed-color';
            const input2 = document.createElement('input');
            input2.type = 'color';
            const isSet = /^#[0-9a-fA-F]{6}$/.test(value || '');
            input2.value = isSet ? value : '#888888';
            if (!isSet) wrap.classList.add('is-unset');
            input2.addEventListener('input', () => {
                wrap.classList.remove('is-unset');
                onChange(input2.value);
            });
            const clear = document.createElement('button');
            clear.type = 'button';
            clear.textContent = '✕';
            clear.title = 'Zurücksetzen (Standard)';
            clear.addEventListener('click', () => {
                wrap.classList.add('is-unset');
                onChange('');
            });
            wrap.appendChild(input2);
            wrap.appendChild(clear);
            return wrap;
        } else if (field.type === 'image') {
            const wrap = document.createElement('div');
            wrap.className = 'ed-image-field';
            const preview = document.createElement('div');
            preview.className = 'ed-image-preview';
            const setPreview = (url) => {
                preview.innerHTML = url ? '<img src="' + esc(url) + '" alt="">' : '<span>Kein Bild</span>';
            };
            setPreview(value);
            const row = document.createElement('div');
            row.className = 'ed-image-row';
            const urlInput = document.createElement('input');
            urlInput.type = 'text';
            urlInput.placeholder = 'Bild-URL';
            urlInput.value = value != null ? value : '';
            urlInput.addEventListener('input', () => { onChange(urlInput.value); setPreview(urlInput.value); });
            const pick = document.createElement('button');
            pick.type = 'button';
            pick.className = 'btn btn-small';
            pick.textContent = 'Mediathek';
            pick.addEventListener('click', () => {
                window.AdminTools.openMediaPicker((url) => {
                    urlInput.value = url;
                    onChange(url);
                    setPreview(url);
                });
            });
            row.appendChild(urlInput);
            row.appendChild(pick);
            wrap.appendChild(preview);
            wrap.appendChild(row);
            return wrap;
        } else if (field.type === 'link') {
            // Link-Ziel: freies Eingabefeld PLUS Seitenwähler aus bestehenden Seiten.
            const wrap = document.createElement('div');
            wrap.className = 'ed-link-row';
            const inp = document.createElement('input');
            inp.type = 'text';
            inp.placeholder = field.placeholder || 'URL oder Seite wählen';
            inp.value = value != null ? value : '';
            inp.addEventListener('input', () => onChange(inp.value));
            const pick = document.createElement('button');
            pick.type = 'button';
            pick.className = 'btn btn-small';
            pick.textContent = 'Seite';
            pick.title = 'Bestehende Seite wählen';
            pick.addEventListener('click', () => {
                if (window.AdminTools && window.AdminTools.openPagePicker) {
                    window.AdminTools.openPagePicker((url) => { inp.value = url; onChange(url); });
                }
            });
            wrap.appendChild(inp);
            wrap.appendChild(pick);
            return wrap;
        } else {
            input = document.createElement('input');
            input.type = field.type === 'number' ? 'number' : 'text';
            input.value = value != null ? value : '';
        }
        input.addEventListener('input', () => {
            onChange(field.type === 'number' ? (input.value === '' ? '' : (parseInt(input.value, 10) || 0)) : input.value);
        });
        return input;
    }

    function addField(container, field, value, onChange) {
        const wrap = document.createElement('div');
        wrap.className = 'form-group';
        if (field.type !== 'checkbox') {
            const label = document.createElement('label');
            label.textContent = field.label;
            wrap.appendChild(label);
        }
        wrap.appendChild(fieldInput(field, value, onChange));
        container.appendChild(wrap);
    }

    function buildInspector() {
        if (selected && selected.kind === 'row') {
            buildRowInspector();
            return;
        }
        if (selected && selected.kind === 'col') {
            buildColInspector();
            return;
        }
        const block = selectedBlock();
        if (!block) { deselect(); return; }
        const def = blockDefs[block.type];
        inspectorBody.innerHTML = '';

        const heading = document.createElement('div');
        heading.className = 'ed-insp-type';
        heading.textContent = def.label;
        inspectorBody.appendChild(heading);

        if (def.items) buildItemsEditor(block, def.items);

        (def.fields || []).forEach((field) => {
            addField(inspectorBody, field, block.data[field.key], (v) => {
                block.data[field.key] = v;
                markDirty();
                scheduleLivePreview();
            });
        });

        // Gestaltung (Abstände & Farben) – für jeden Block, ohne CSS-Kenntnisse.
        const styleDetails = document.createElement('details');
        styleDetails.className = 'ed-style-details';
        const summary = document.createElement('summary');
        summary.textContent = '🎨 Gestaltung des Blocks – Hintergrund, Textfarbe, Ecken, Ausrichtung, Abstände';
        styleDetails.appendChild(summary);
        if (!block.data._style || typeof block.data._style !== 'object' || Array.isArray(block.data._style)) {
            block.data._style = {};
        }
        const styleObj = block.data._style;

        // Die gewählte Geräte-Ansicht bestimmt, für welche Displaygröße
        // gestaltet wird: Desktop = alle Größen, Handy/Tablet = nur mobil.
        const mobileEditing = canvas.classList.contains('is-phone') || canvas.classList.contains('is-tablet');
        const RESPONSIVE_KEYS = { mt: 'mmt', mb: 'mmb', p: 'mp', align: 'malign' };

        const modeNote = document.createElement('p');
        modeNote.className = 'ed-mode-note' + (mobileEditing ? ' is-mobile' : '');
        modeNote.textContent = mobileEditing
            ? '📱 Du gestaltest die MOBILE Ansicht – Änderungen hier gelten nur für Bildschirme unter 768 px. Leer = wie Desktop.'
            : '🖥 Du gestaltest für ALLE Displaygrößen. Tipp: In der Handy-/Tablet-Ansicht (oben) gelten Änderungen nur für mobil.';
        styleDetails.appendChild(modeNote);

        STYLE_FIELDS.forEach((field) => {
            let key = field.key;
            let label = field.label;
            if (mobileEditing) {
                if (!RESPONSIVE_KEYS[key]) return; // Farben/Rundung gelten für alle Größen
                key = RESPONSIVE_KEYS[key];
                label += ' – nur mobil';
            }
            addField(styleDetails, Object.assign({}, field, { label: label }), styleObj[key], (v) => {
                if (v === '' || v === 0) delete styleObj[key];
                else styleObj[key] = v;
                markDirty();
                if (field.key === 'disp') {
                    const be = canvas.querySelector('.ed-block.is-selected');
                    if (be) be.classList.toggle('ed-block-inline', isInlineBlock(block));
                }
                scheduleLivePreview();
            });
        });

        if (!mobileEditing) {
            const animHint = document.createElement('p');
            animHint.className = 'muted small';
            animHint.textContent = 'Scroll-Animationen sind im Editor nicht sichtbar – auf der Website (Vorschau ↗) schon.';
            styleDetails.appendChild(animHint);
        }

        if (mobileEditing) {
            const hint = document.createElement('p');
            hint.className = 'muted small';
            hint.textContent = 'Farben und Eckenrundung gelten für alle Größen – dafür in die Desktop-Ansicht wechseln.';
            styleDetails.appendChild(hint);
            if (Object.keys(styleObj).some((k) => ['malign', 'mmt', 'mmb', 'mp'].includes(k))) {
                const reset = document.createElement('button');
                reset.type = 'button';
                reset.className = 'btn btn-small btn-block';
                reset.textContent = '↺ Mobile Anpassungen entfernen (wie Desktop)';
                reset.addEventListener('click', () => {
                    ['malign', 'mmt', 'mmb', 'mp'].forEach((k) => delete styleObj[k]);
                    markDirty();
                    scheduleLivePreview();
                    buildInspector();
                });
                styleDetails.appendChild(reset);
            }
        }

        // Standardmäßig geöffnet, damit die Block-Gestaltung (Hintergrund,
        // Textfarbe, Ecken, Ausrichtung …) direkt sichtbar und auffindbar ist.
        styleDetails.open = true;
        inspectorBody.appendChild(styleDetails);

        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'btn btn-danger btn-block';
        del.textContent = 'Block löschen';
        del.addEventListener('click', () => {
            const s = selected;
            deleteBlock(s.r, s.c, s.b);
        });
        inspectorBody.appendChild(del);
    }

    function buildRowInspector() {
        const row = state.rows[selected.r];
        if (!row) { deselect(); return; }
        inspectorBody.innerHTML = '';

        const heading = document.createElement('div');
        heading.className = 'ed-insp-type';
        heading.textContent = 'Zeile ' + (selected.r + 1) + ' – Gestaltung';
        inspectorBody.appendChild(heading);

        if (!row.style || typeof row.style !== 'object' || Array.isArray(row.style)) row.style = {};
        const style = row.style;

        const note = document.createElement('p');
        note.className = 'muted small';
        note.textContent = 'Die Hintergrundfarbe füllt auf der Website immer die volle Browserbreite (farbige Sektion). „Volle Seitenbreite“ lässt zusätzlich die Inhalte bis an den Rand laufen.';
        inspectorBody.appendChild(note);

        // Breite des Bereichs
        addField(inspectorBody, {
            key: 'width', label: 'Breite des Bereichs', type: 'select',
            options: [['', 'So breit wie der Inhaltsbereich'], ['full', 'Volle Seitenbreite']],
        }, style.width, (v) => {
            if (v === '') delete style.width;
            else style.width = v;
            markDirty();
            applyRowStyles();
        });

        // Hintergrund: Layout-Farbe oder eigene Farbe
        const bgModes = [
            ['', 'Keine'],
            ['primary', 'Hauptfarbe (aus Gestaltung)'],
            ['accent', 'Akzentfarbe (aus Gestaltung)'],
            ['surface', 'Flächenfarbe (aus Gestaltung)'],
            ['page', 'Seitenhintergrund (aus Gestaltung)'],
            ['custom', 'Eigene Farbe …'],
        ];
        const currentBg = style.bg || '';
        const currentMode = /^#/.test(currentBg) ? 'custom' : currentBg;

        const colorWrap = document.createElement('div');
        const renderColorField = () => {
            colorWrap.innerHTML = '';
            if (!/^#/.test(style.bg || '')) return;
            addField(colorWrap, { key: 'bg', label: 'Eigene Hintergrundfarbe', type: 'colorclear' }, style.bg, (v) => {
                if (v === '') delete style.bg;
                else style.bg = v;
                markDirty();
                applyRowStyles();
            });
        };

        addField(inspectorBody, {
            key: 'bgmode', label: 'Hintergrundfarbe', type: 'select', options: bgModes,
        }, currentMode, (v) => {
            if (v === '') delete style.bg;
            else if (v === 'custom') style.bg = /^#/.test(style.bg || '') ? style.bg : '#f4ede4';
            else style.bg = v;
            markDirty();
            applyRowStyles();
            renderColorField();
        });
        inspectorBody.appendChild(colorWrap);
        renderColorField();

        [
            { key: 'pt', label: 'Innenabstand oben (px)', type: 'number' },
            { key: 'pb', label: 'Innenabstand unten (px)', type: 'number' },
        ].forEach((field) => {
            addField(inspectorBody, field, style[field.key], (v) => {
                if (v === '' || v === 0) delete style[field.key];
                else style[field.key] = v;
                markDirty();
                applyRowStyles();
            });
        });

        // Form der farbigen Sektion (Schräge/Welle) + eigene Eckenrundung.
        // Nur sinnvoll, wenn die Zeile eine Hintergrundfarbe/Vollbreite hat.
        var shapeOpts = [['', 'Gerade'], ['slant', 'Schräge'], ['wave', 'Welle']];
        addField(inspectorBody, { key: 'st', label: 'Form oben (bei Hintergrundfarbe)', type: 'select', options: shapeOpts }, style.st, (v) => {
            if (v === '') delete style.st; else style.st = v;
            markDirty();
        });
        addField(inspectorBody, { key: 'sb', label: 'Form unten (bei Hintergrundfarbe)', type: 'select', options: shapeOpts }, style.sb, (v) => {
            if (v === '') delete style.sb; else style.sb = v;
            markDirty();
        });
        addField(inspectorBody, { key: 'radius', label: 'Eckenrundung (px, leer = automatisch nach Design)', type: 'number' }, style.radius, (v) => {
            if (v === '') delete style.radius; else style.radius = v;
            markDirty();
        });
        var shapeHint = document.createElement('p');
        shapeHint.className = 'muted small';
        shapeHint.textContent = 'Schräge/Welle und Eckenrundung wirken auf farbige Sektionen und erscheinen auf der Website (Vorschau über „Website ansehen").';
        inspectorBody.appendChild(shapeHint);

        // Mobiles Verhalten der Spalten – verständliche Auswahl.
        // Intern gesteuert über style.bp: leer = Standard (768 px),
        // 0 = nie stapeln (bleiben nebeneinander), N = ab N px stapeln.
        const bpMode = () => {
            if (style.bp === '' || style.bp === undefined || style.bp === null) return '';
            return Number(style.bp) === 0 ? 'side' : 'custom';
        };
        const bpWrap = document.createElement('div');
        const renderBpCustom = () => {
            bpWrap.innerHTML = '';
            if (bpMode() !== 'custom') return;
            addField(bpWrap, {
                key: 'bp', label: 'Untereinander ab … px Bildschirmbreite', type: 'number',
            }, style.bp, (v) => {
                style.bp = (v === '' ? 600 : v);
                markDirty();
                applyRowStyles();
            });
            const hint = document.createElement('p');
            hint.className = 'muted small';
            hint.textContent = 'Schmaler als dieser Wert werden die Spalten untereinander gestapelt.';
            bpWrap.appendChild(hint);
        };

        addField(inspectorBody, {
            key: 'bpmode', label: 'Spalten auf dem Smartphone', type: 'select',
            options: [
                ['', 'Automatisch untereinander (empfohlen)'],
                ['side', 'Nebeneinander lassen'],
                ['custom', 'Ab eigener Breite stapeln …'],
            ],
        }, bpMode(), (v) => {
            if (v === '') delete style.bp;
            else if (v === 'side') style.bp = 0;
            else if (v === 'custom') style.bp = (Number(style.bp) > 0 ? style.bp : 600);
            markDirty();
            renderBpCustom();
            applyRowStyles();
        });
        inspectorBody.appendChild(bpWrap);
        renderBpCustom();

        const bpHint = document.createElement('p');
        bpHint.className = 'muted small';
        bpHint.textContent = '„Nebeneinander lassen" hält die Spalten auch auf schmalen Handys in einer Reihe. Nutze die Smartphone-Vorschau oben zum Prüfen.';
        inspectorBody.appendChild(bpHint);
    }

    // Inspektor für eine SPALTE (der 6/12-Block um die Inhalte): Breite +
    // eigene Gestaltung (Hintergrund, Innenabstand, Ausrichtung, Ecken).
    function buildColInspector() {
        const row = state.rows[selected.r];
        const col = row && row.columns[selected.c];
        if (!col) { deselect(); return; }
        inspectorBody.innerHTML = '';

        const heading = document.createElement('div');
        heading.className = 'ed-insp-type';
        heading.textContent = 'Spalte ' + (selected.c + 1) + ' – Gestaltung';
        inspectorBody.appendChild(heading);

        const note = document.createElement('p');
        note.className = 'muted small';
        note.textContent = 'Die Spalte ist der Bereich um die Inhalte (Breite 1–12). Hier gibst du ihr Breite, Hintergrund, Innenabstand, Ausrichtung und runde Ecken – z. B. für eine farbige Karte.';
        inspectorBody.appendChild(note);

        // Breite (Spalten von 12)
        const widthOpts = [];
        for (let i = 1; i <= 12; i++) widthOpts.push([String(i), i + ' / 12']);
        addField(inspectorBody, { key: 'span', label: 'Breite (Spalten von 12)', type: 'select', options: widthOpts }, String(col.span || 12), (v) => {
            col.span = Math.min(12, Math.max(1, parseInt(v, 10) || 12));
            markDirty();
            render();
            selectCol(selected.r, selected.c);
        });

        if (!col.style || typeof col.style !== 'object' || Array.isArray(col.style)) col.style = {};
        const style = col.style;

        // Hintergrund: Layout-Farbe oder eigene Farbe (wie bei der Zeile).
        const bgModes = [
            ['', 'Keine'],
            ['primary', 'Hauptfarbe (aus Gestaltung)'],
            ['accent', 'Akzentfarbe (aus Gestaltung)'],
            ['surface', 'Flächenfarbe (aus Gestaltung)'],
            ['page', 'Seitenhintergrund (aus Gestaltung)'],
            ['custom', 'Eigene Farbe …'],
        ];
        const currentMode = /^#/.test(style.bg || '') ? 'custom' : (style.bg || '');
        const colorWrap = document.createElement('div');
        const renderColorField = () => {
            colorWrap.innerHTML = '';
            if (!/^#/.test(style.bg || '')) return;
            addField(colorWrap, { key: 'bg', label: 'Eigene Hintergrundfarbe', type: 'colorclear' }, style.bg, (v) => {
                if (v === '') delete style.bg; else style.bg = v;
                markDirty();
                applyColStyles();
            });
        };
        addField(inspectorBody, { key: 'bgmode', label: 'Hintergrundfarbe', type: 'select', options: bgModes }, currentMode, (v) => {
            if (v === '') delete style.bg;
            else if (v === 'custom') style.bg = /^#/.test(style.bg || '') ? style.bg : '#f4ede4';
            else style.bg = v;
            markDirty();
            applyColStyles();
            renderColorField();
        });
        inspectorBody.appendChild(colorWrap);
        renderColorField();

        addField(inspectorBody, { key: 'p', label: 'Innenabstand (px)', type: 'number' }, style.p, (v) => {
            if (v === '' || v === 0) delete style.p; else style.p = v;
            markDirty();
            applyColStyles();
        });
        addField(inspectorBody, { key: 'align', label: 'Textausrichtung', type: 'select', options: [['', 'Standard'], ['left', 'Links'], ['center', 'Zentriert'], ['right', 'Rechts']] }, style.align, (v) => {
            if (v === '') delete style.align; else style.align = v;
            markDirty();
            applyColStyles();
        });
        addField(inspectorBody, { key: 'valign', label: 'Vertikale Ausrichtung (bei unterschiedlich hohen Spalten)', type: 'select', options: [['', 'Standard (oben)'], ['top', 'Oben'], ['center', 'Mittig'], ['bottom', 'Unten']] }, style.valign, (v) => {
            if (v === '') delete style.valign; else style.valign = v;
            markDirty();
        });
        addField(inspectorBody, { key: 'radius', label: 'Eckenrundung (px)', type: 'number' }, style.radius, (v) => {
            if (v === '' || v === 0) delete style.radius; else style.radius = v;
            markDirty();
            applyColStyles();
        });

        const vHint = document.createElement('p');
        vHint.className = 'muted small';
        vHint.textContent = 'Die vertikale Ausrichtung wirkt auf der Website, wenn die Spalten einer Zeile unterschiedlich hoch sind (Vorschau über „Website ansehen").';
        inspectorBody.appendChild(vHint);

        if (row.columns.length > 1) {
            const del = document.createElement('button');
            del.type = 'button';
            del.className = 'btn btn-danger btn-block';
            del.textContent = 'Spalte löschen';
            del.addEventListener('click', () => colAction('col-del', selected.r, selected.c));
            inspectorBody.appendChild(del);
        }
    }

    /** Editor für Element-Listen (Galerie-Bilder, Slides, Akkordeon-Abschnitte). */
    function buildItemsEditor(block, spec) {
        const container = document.createElement('div');
        container.className = 'ed-items';

        const heading = document.createElement('label');
        heading.textContent = spec.label;
        container.appendChild(heading);

        const list = document.createElement('div');
        container.appendChild(list);

        const items = () => {
            if (!Array.isArray(block.data[spec.key])) block.data[spec.key] = [];
            return block.data[spec.key];
        };

        const rebuild = () => {
            list.innerHTML = '';
            items().forEach((item, idx) => {
                const itemEl = document.createElement('div');
                itemEl.className = 'ed-item';
                const head = document.createElement('div');
                head.className = 'ed-item-head';
                head.innerHTML = '<span>' + spec.itemLabel + ' ' + (idx + 1) + '</span>';
                const tools = document.createElement('span');
                [['↑', () => { if (idx > 0) { const a = items(); [a[idx - 1], a[idx]] = [a[idx], a[idx - 1]]; changed(); } }],
                 ['↓', () => { const a = items(); if (idx < a.length - 1) { [a[idx], a[idx + 1]] = [a[idx + 1], a[idx]]; changed(); } }],
                 ['✕', () => { items().splice(idx, 1); changed(); }]].forEach(([txt, fn]) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = txt;
                    if (txt === '✕') b.className = 'danger';
                    b.addEventListener('click', fn);
                    tools.appendChild(b);
                });
                head.appendChild(tools);
                itemEl.appendChild(head);

                spec.fields.forEach((field) => {
                    addField(itemEl, field, item[field.key], (v) => {
                        item[field.key] = v;
                        markDirty();
                        scheduleLivePreview();
                    });
                });
                list.appendChild(itemEl);
            });
        };

        const changed = () => {
            markDirty();
            scheduleLivePreview();
            rebuild();
        };

        const hasImage = spec.fields.some((f) => f.type === 'image');
        const add = document.createElement('button');
        add.type = 'button';
        add.className = 'btn btn-small btn-block';
        add.textContent = '+ ' + spec.itemLabel + ' hinzufügen';
        add.addEventListener('click', () => {
            const pushItem = (url) => {
                const item = {};
                spec.fields.forEach((f) => { item[f.key] = ''; });
                if (url) item[spec.fields.find((f) => f.type === 'image').key] = url;
                items().push(item);
                changed();
            };
            if (hasImage) window.AdminTools.openMediaPicker(pushItem);
            else pushItem(null);
        });
        container.appendChild(add);

        rebuild();
        inspectorBody.appendChild(container);
    }

    /* ---------- Zeilen & Spalten ---------- */

    function addRowAt(spans, at) {
        state.rows.splice(at, 0, {
            id: uid('row'),
            columns: spans.map((span) => ({ id: uid('col'), span: span, blocks: [] })),
        });
        markDirty();
        render();
    }

    function addRow(spans) {
        addRowAt(spans, state.rows.length);
        canvas.parentElement.scrollTop = canvas.parentElement.scrollHeight;
    }

    async function rowAction(act, r) {
        const rows = state.rows;
        if (act === 'row-up' && r > 0) {
            [rows[r - 1], rows[r]] = [rows[r], rows[r - 1]];
        } else if (act === 'row-down' && r < rows.length - 1) {
            [rows[r], rows[r + 1]] = [rows[r + 1], rows[r]];
        } else if (act === 'row-del') {
            const hasContent = rows[r].columns.some((col) => col.blocks.length);
            if (hasContent && !(await window.AdminDialog.confirm('Diese Zeile enthält Inhalte. Wirklich löschen?', { danger: true, confirmText: 'Löschen' }))) return;
            rows.splice(r, 1);
        } else if (act === 'col-add') {
            const used = rows[r].columns.reduce((sum, col) => sum + col.span, 0);
            rows[r].columns.push({ id: uid('col'), span: Math.min(Math.max(12 - used, 1), 12), blocks: [] });
        } else if (act === 'row-dup') {
            if (rows[r].columns.some((col) => col.blocks.some((bl) => bl.type === 'l-content'))) {
                window.AdminDialog.alert('Diese Zeile enthält den Inhaltsbereich – er kann nur einmal im Layout vorkommen.');
                return;
            }
            const copy = JSON.parse(JSON.stringify(rows[r]));
            copy.id = uid('row');
            copy.columns.forEach((col) => {
                col.id = uid('col');
                col.blocks.forEach((bl) => { bl.id = uid('b'); });
            });
            rows.splice(r + 1, 0, copy);
        } else {
            return;
        }
        deselect();
        markDirty();
        render();
    }

    async function colAction(act, r, c) {
        const cols = state.rows[r].columns;
        const col = cols[c];
        if (act === 'wider') {
            col.span = Math.min(12, col.span + 1);
        } else if (act === 'narrower') {
            col.span = Math.max(1, col.span - 1);
        } else if (act === 'col-del') {
            if (cols.length === 1) {
                window.AdminDialog.alert('Die letzte Spalte einer Zeile kann nicht gelöscht werden. Lösche stattdessen die Zeile.');
                return;
            }
            if (col.blocks.length && !(await window.AdminDialog.confirm('Diese Spalte enthält Inhalte. Wirklich löschen?', { danger: true, confirmText: 'Löschen' }))) return;
            cols.splice(c, 1);
        } else {
            return;
        }
        deselect();
        markDirty();
        render();
    }

    /* ---------- Auswahl-Dialoge (Block- & Zeilen-Picker) ---------- */

    function openPickerOverlay(title, build) {
        const overlay = document.createElement('div');
        overlay.className = 'edp-overlay';
        const panel = document.createElement('div');
        panel.className = 'edp';
        const close = () => {
            overlay.remove();
            document.removeEventListener('keydown', onKey);
        };
        const onKey = (e) => { if (e.key === 'Escape') close(); };
        document.addEventListener('keydown', onKey);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });

        const head = document.createElement('div');
        head.className = 'edp-head';
        head.innerHTML = '<strong>' + esc(title) + '</strong>';
        const x = document.createElement('button');
        x.type = 'button';
        x.className = 'edp-close';
        x.textContent = '✕';
        x.addEventListener('click', close);
        head.appendChild(x);
        panel.appendChild(head);

        build(panel, close);
        overlay.appendChild(panel);
        document.body.appendChild(overlay);
    }

    function openBlockPicker(onPick) {
        openPickerOverlay('Block einfügen', (panel, close) => {
            const body = document.createElement('div');
            body.className = 'edp-body';
            const search = document.createElement('input');
            search.type = 'search';
            search.className = 'edp-search';
            search.placeholder = 'Block suchen …';
            body.appendChild(search);
            const listWrap = document.createElement('div');
            body.appendChild(listWrap);

            const cats = blockCategories();
            const renderList = (q) => {
                listWrap.innerHTML = '';
                q = (q || '').toLowerCase();
                cats.forEach(([catLabel, types]) => {
                    const matches = types.filter((t) => blockDefs[t] && blockDefs[t].label.toLowerCase().includes(q));
                    if (!matches.length) return;
                    const cat = document.createElement('div');
                    cat.className = 'edp-cat';
                    cat.textContent = catLabel;
                    listWrap.appendChild(cat);
                    const grid = document.createElement('div');
                    grid.className = 'edp-grid';
                    matches.forEach((type) => {
                        const def = blockDefs[type];
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'edp-item';
                        item.innerHTML = '<span class="ed-block-icon">' + def.icon + '</span>' + esc(def.label);
                        item.addEventListener('click', () => { close(); onPick(type); });
                        grid.appendChild(item);
                    });
                    listWrap.appendChild(grid);
                });
                if (!listWrap.children.length) {
                    listWrap.innerHTML = '<p class="muted small">Nichts gefunden.</p>';
                }
            };
            search.addEventListener('input', () => renderList(search.value));
            renderList('');
            panel.appendChild(body);
            setTimeout(() => search.focus(), 40);
        });
    }

    function openRowPicker(at) {
        openPickerOverlay('Zeile hinzufügen', (panel, close) => {
            const body = document.createElement('div');
            body.className = 'edp-body';

            if (editorMode === 'page') {
                const cat = document.createElement('div');
                cat.className = 'edp-cat';
                cat.textContent = 'Fertige Sektionen';
                body.appendChild(cat);
                const grid = document.createElement('div');
                grid.className = 'edp-sections';
                SECTIONS.forEach((section) => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'edp-section';
                    item.innerHTML = '<strong>' + section.icon + ' ' + esc(section.title) + '</strong><span>' + esc(section.desc) + '</span>';
                    item.addEventListener('click', () => {
                        state.rows.splice(at, 0, section.build());
                        markDirty();
                        render();
                        close();
                    });
                    grid.appendChild(item);
                });
                body.appendChild(grid);
            }

            const cat = document.createElement('div');
            cat.className = 'edp-cat';
            cat.textContent = 'Leere Zeile mit Spalten';
            body.appendChild(cat);
            const grid = document.createElement('div');
            grid.className = 'edp-presets';
            presets.forEach((preset) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'edp-preset';
                btn.title = preset.title;
                preset.spans.forEach((span) => {
                    const bar = document.createElement('span');
                    bar.style.flexGrow = span;
                    btn.appendChild(bar);
                });
                btn.addEventListener('click', () => { addRowAt(preset.spans, at); close(); });
                grid.appendChild(btn);
            });
            body.appendChild(grid);
            panel.appendChild(body);
        });
    }

    /* ---------- Spaltenbreite per Ziehen ---------- */

    function bindColResizer(resizer, colEl, colBar, r, c) {
        resizer.addEventListener('pointerdown', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const colsEl = colEl.parentElement;
            const unit = colsEl.getBoundingClientRect().width / 12;
            const col = state.rows[r].columns[c];
            const startSpan = col.span;
            const startX = e.clientX;
            resizer.classList.add('is-resizing');
            resizer.setPointerCapture(e.pointerId);
            const move = (ev) => {
                const span = Math.max(1, Math.min(12, startSpan + Math.round((ev.clientX - startX) / unit)));
                if (span !== col.span) {
                    col.span = span;
                    colEl.style.setProperty('--span', span);
                    const label = colBar.querySelector('.ed-col-width');
                    if (label) label.textContent = span + '/12';
                }
            };
            const up = () => {
                resizer.classList.remove('is-resizing');
                resizer.removeEventListener('pointermove', move);
                resizer.removeEventListener('pointerup', up);
                if (col.span !== startSpan) markDirty();
            };
            resizer.addEventListener('pointermove', move);
            resizer.addEventListener('pointerup', up);
        });
    }

    /* ---------- Drag & Drop: Blöcke ---------- */

    function bindDropzone(zone, r, c) {
        zone.addEventListener('dragover', (e) => {
            if (!dragData || dragData.kind === 'row') return;
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = dragData.kind === 'new' ? 'copy' : 'move';
            zone.classList.add('is-over');
            showInsertHint(zone, e.clientY);
        });
        zone.addEventListener('dragleave', (e) => {
            if (!zone.contains(e.relatedTarget)) {
                zone.classList.remove('is-over');
                clearInsertHints(zone);
            }
        });
        zone.addEventListener('drop', (e) => {
            if (!dragData || dragData.kind === 'row') return;
            e.preventDefault();
            e.stopPropagation();
            const index = insertIndex(zone, e.clientY);
            const target = state.rows[r].columns[c];

            if (dragData.kind === 'new') {
                // Der Inhaltsbereich darf im Layout nur einmal vorkommen.
                if (dragData.type === 'l-content' && countBlocksOfType('l-content') > 0) {
                    window.AdminDialog.alert('Der Inhaltsbereich ist bereits im Layout vorhanden – er kann nur einmal eingesetzt werden. Verschiebe ihn stattdessen an die gewünschte Stelle.');
                    dragData = null;
                    clearDropHints();
                    return;
                }
                target.blocks.splice(index, 0, newBlock(dragData.type));
            } else {
                const from = dragData.from;
                const source = state.rows[from.r].columns[from.c];
                const moved = source.blocks.splice(from.b, 1)[0];
                let insertAt = index;
                if (source === target && from.b < insertAt) insertAt--;
                target.blocks.splice(insertAt, 0, moved);
            }
            dragData = null;
            deselect();
            markDirty();
            render();
        });
    }

    /* ---------- Drag & Drop: Zeilen ---------- */

    function bindRowDropzone(rowEl, r) {
        rowEl.addEventListener('dragover', (e) => {
            if (!dragData || dragData.kind !== 'row') return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            const rect = rowEl.getBoundingClientRect();
            const before = e.clientY < rect.top + rect.height / 2;
            rowEl.classList.toggle('row-insert-before', before);
            rowEl.classList.toggle('row-insert-after', !before);
        });
        rowEl.addEventListener('dragleave', () => {
            rowEl.classList.remove('row-insert-before', 'row-insert-after');
        });
        rowEl.addEventListener('drop', (e) => {
            if (!dragData || dragData.kind !== 'row') return;
            e.preventDefault();
            const rect = rowEl.getBoundingClientRect();
            const before = e.clientY < rect.top + rect.height / 2;
            const from = dragData.from;
            let to = r + (before ? 0 : 1);
            if (from < to) to--;
            dragData = null;
            if (from !== to) {
                const moved = state.rows.splice(from, 1)[0];
                state.rows.splice(to, 0, moved);
                markDirty();
            }
            deselect();
            render();
        });
    }

    function blockElements(zone) {
        return Array.from(zone.querySelectorAll(':scope > .ed-block'));
    }

    function insertIndex(zone, y) {
        const blocks = blockElements(zone);
        for (let i = 0; i < blocks.length; i++) {
            const rect = blocks[i].getBoundingClientRect();
            if (y < rect.top + rect.height / 2) return i;
        }
        return blocks.length;
    }

    function showInsertHint(zone, y) {
        clearInsertHints(zone);
        const blocks = blockElements(zone);
        const index = insertIndex(zone, y);
        if (index < blocks.length) {
            blocks[index].classList.add('insert-before');
        } else if (blocks.length) {
            blocks[blocks.length - 1].classList.add('insert-after');
        }
    }

    function clearInsertHints(zone) {
        zone.querySelectorAll('.insert-before, .insert-after').forEach((el) => {
            el.classList.remove('insert-before', 'insert-after');
        });
    }

    function clearDropHints() {
        canvas.querySelectorAll('.ed-blocks.is-over').forEach((el) => el.classList.remove('is-over'));
        canvas.querySelectorAll('.insert-before, .insert-after, .row-insert-before, .row-insert-after').forEach((el) => {
            el.classList.remove('insert-before', 'insert-after', 'row-insert-before', 'row-insert-after');
        });
    }

    /* ---------- Palette & Presets ---------- */

    Object.keys(blockDefs).forEach((type) => {
        const def = blockDefs[type];
        const item = document.createElement('div');
        item.className = 'ed-palette-item';
        item.draggable = true;
        item.innerHTML = '<span class="ed-block-icon">' + def.icon + '</span>' + esc(def.label);
        item.addEventListener('dragstart', (e) => {
            dragData = { kind: 'new', type: type };
            e.dataTransfer.effectAllowed = 'copy';
            e.dataTransfer.setData('text/plain', 'new:' + type);
        });
        item.addEventListener('dragend', () => {
            dragData = null;
            clearDropHints();
        });
        paletteWrap.appendChild(item);
    });

    const presetLabel = document.createElement('span');
    presetLabel.className = 'ed-presets-label';
    presetLabel.textContent = '+ Zeile:';
    presetBar.appendChild(presetLabel);
    presets.forEach((preset) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ed-preset';
        btn.title = preset.title;
        preset.spans.forEach((span) => {
            const bar = document.createElement('span');
            bar.style.flexGrow = span;
            btn.appendChild(bar);
        });
        btn.addEventListener('click', () => addRow(preset.spans));
        presetBar.appendChild(btn);
    });

    /* ---------- Eigenes CSS (nur diese Seite) ---------- */

    cssInput.value = state.css || '';
    pageCssTag.textContent = state.css || '';
    cssBtn.addEventListener('click', () => {
        cssPanel.hidden = !cssPanel.hidden;
        cssBtn.classList.toggle('is-active', !cssPanel.hidden);
        if (!cssPanel.hidden) cssInput.focus();
    });
    cssInput.addEventListener('input', () => {
        state.css = cssInput.value;
        pageCssTag.textContent = state.css;
        markDirty();
    });

    /* ---------- Speichern ---------- */

    async function save() {
        saveBtn.disabled = true;
        statusEl.textContent = 'Speichern…';
        statusEl.className = 'ed-status';
        try {
            const res = await fetch(saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify(state),
            });
            const json = await res.json();
            if (!res.ok || !json.ok) throw new Error(json.error || 'HTTP ' + res.status);
            dirty = false;
            statusEl.textContent = 'Gespeichert ✓';
            statusEl.className = 'ed-status is-saved';
            setTimeout(() => { if (!dirty) statusEl.textContent = ''; }, 3000);
        } catch (err) {
            statusEl.textContent = 'Fehler beim Speichern: ' + err.message;
            statusEl.className = 'ed-status is-error';
        } finally {
            saveBtn.disabled = false;
        }
    }

    saveBtn.addEventListener('click', save);

    // Geräte-Vorschau: Canvas auf Tablet-/Smartphone-Breite umschalten.
    const deviceBar = root.querySelector('.ed-devices');
    if (deviceBar) {
        deviceBar.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-device]');
            if (!btn) return;
            deviceBar.querySelectorAll('button').forEach((el) => el.classList.toggle('is-active', el === btn));
            canvas.classList.remove('is-tablet', 'is-phone');
            if (btn.dataset.device === 'tablet') canvas.classList.add('is-tablet');
            if (btn.dataset.device === 'phone') canvas.classList.add('is-phone');
            // Gestaltungs-Panel an die neue Ansicht anpassen (Desktop = alle
            // Größen, Handy/Tablet = nur mobile Werte).
            if (selected) buildInspector();
        });
    }

    // Tastenkürzel: Strg+S speichern, Strg+Z/Y rückgängig/wiederholen,
    // Strg+D dupliziert, Entf löscht die Auswahl, Esc hebt sie auf.
    document.addEventListener('keydown', (e) => {
        const target = e.target;
        const isTyping = target && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName || ''));
        const mod = e.ctrlKey || e.metaKey;
        const key = (e.key || '').toLowerCase();

        if (mod && key === 's') {
            e.preventDefault();
            save();
            return;
        }
        if (isTyping) return;

        if (mod && key === 'z' && !e.shiftKey) { e.preventDefault(); undo(); return; }
        if (mod && (key === 'y' || (key === 'z' && e.shiftKey))) { e.preventDefault(); redo(); return; }
        if (mod && key === 'd' && selected) {
            e.preventDefault();
            if (selected.kind === 'block') duplicateBlock(selected.r, selected.c, selected.b);
            else rowAction('row-dup', selected.r);
            return;
        }
        if ((e.key === 'Delete' || e.key === 'Backspace') && selected && selected.kind === 'block') {
            e.preventDefault();
            deleteBlock(selected.r, selected.c, selected.b);
            return;
        }
        if (e.key === 'Delete' && selected && selected.kind === 'row') {
            e.preventDefault();
            rowAction('row-del', selected.r);
            return;
        }
        if (e.key === 'Escape') deselect();
    });
    window.addEventListener('beforeunload', (e) => {
        if (dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    canvas.addEventListener('click', (e) => {
        if (e.target === canvas) deselect();
    });

    /* ---------- Kompakter Editor: Elemente-Schublade + Eigenschaften-Pop-up ----------
       Auf schmalen Screens (Tablet/Handy) ist die Elemente-Liste ausblendbar und
       die Eigenschaften erscheinen als Bottom-Sheet, sobald man etwas anklickt. */
    const compactMq = window.matchMedia('(max-width: 1024px)');
    const paletteEl = document.querySelector('.ed-palette');
    const inspectorEl = document.querySelector('.ed-inspector');
    const drawerBackdrop = document.getElementById('ed-drawer-backdrop');

    function updateBackdrop() {
        if (!drawerBackdrop) return;
        const open = compactMq.matches
            && ((paletteEl && paletteEl.classList.contains('is-open'))
                || (inspectorEl && inspectorEl.classList.contains('is-open')));
        drawerBackdrop.hidden = !open;
    }
    function closePalette() { if (paletteEl) paletteEl.classList.remove('is-open'); updateBackdrop(); }
    function openPalette() {
        if (paletteEl) { if (inspectorEl) inspectorEl.classList.remove('is-open'); paletteEl.classList.add('is-open'); }
        updateBackdrop();
    }
    function closeInspector() { if (inspectorEl) inspectorEl.classList.remove('is-open'); updateBackdrop(); }
    function openInspector() {
        if (compactMq.matches && inspectorEl) {
            if (paletteEl) paletteEl.classList.remove('is-open');
            inspectorEl.classList.add('is-open');
            updateBackdrop();
        }
    }

    (function wireDrawers() {
        const toggle = document.getElementById('ed-palette-toggle');
        const inspClose = document.getElementById('ed-insp-close');
        if (toggle) {
            toggle.addEventListener('click', () => {
                if (paletteEl && paletteEl.classList.contains('is-open')) closePalette();
                else openPalette();
            });
        }
        if (inspClose) inspClose.addEventListener('click', () => { deselect(); });
        if (drawerBackdrop) drawerBackdrop.addEventListener('click', () => { closePalette(); closeInspector(); });
        compactMq.addEventListener('change', () => { closePalette(); closeInspector(); });
    })();

    render();
})();
