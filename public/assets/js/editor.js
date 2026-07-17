/**
 * Drag-&-Drop-Inhalts-Editor.
 *
 * Struktur des Inhalts: state.rows[] → columns[] (span im 12er-Raster) → blocks[].
 * Neue Block-Typen: Eintrag in blockDefs ergänzen und serverseitig in
 * app/Core/BlockRegistry.php registrieren.
 *
 * Feldtypen im Inspektor: text, number, textarea, richtext, select, checkbox,
 * image (mit Mediathek-Auswahl). Zusätzlich kann ein Block eine Element-Liste
 * haben (items, z. B. Bilder einer Galerie oder Slides), deren Einträge eigene
 * Felder besitzen und sortierbar sind.
 */
(function () {
    'use strict';

    const root = document.getElementById('editor');
    if (!root) return;

    const saveUrl = root.dataset.saveUrl;
    const csrf = root.dataset.csrf;

    let state = { rows: [] };
    try {
        const initial = JSON.parse(document.getElementById('editor-data').textContent);
        if (initial && Array.isArray(initial.rows)) state = initial;
    } catch (e) { /* leerer Editor */ }

    let uidCounter = 0;
    const uid = (prefix) => prefix + '-' + Date.now().toString(36) + '-' + (uidCounter++);

    const VARIANTS = {
        heading: [['standard', 'Standard'], ['accent-line', 'Mit Akzentlinie'], ['boxed', 'Farbig hinterlegt'], ['centered', 'Zentriert']],
        text: [['standard', 'Standard'], ['infobox', 'Infobox'], ['note', 'Hinweis (Akzentfarbe)']],
        image: [['standard', 'Standard'], ['frame', 'Mit Rahmen'], ['shadow', 'Mit Schatten'], ['round', 'Stark gerundet']],
        gallery: [['standard', 'Standard'], ['cards', 'Karten'], ['seamless', 'Randlos dicht']],
        quote: [['standard', 'Standard'], ['card', 'Karte'], ['big', 'Groß & zentriert']],
        accordion: [['standard', 'Standard'], ['cards', 'Karten']],
    };

    const variantField = (type) => ({ key: 'variant', label: 'Designvorlage', type: 'select', options: VARIANTS[type] });

    const blockDefs = {
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
                { key: 'link', label: 'Verlinkung (optional)', type: 'text' },
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
            defaults: { slides: [], height: 65, overlay: 'medium', autoplay: 1, interval: 6, arrows: 1, dots: 1 },
            items: {
                key: 'slides', label: 'Slides', itemLabel: 'Slide',
                fields: [
                    { key: 'src', label: 'Hintergrundbild', type: 'image' },
                    { key: 'title', label: 'Überschrift', type: 'text' },
                    { key: 'text', label: 'Text', type: 'textarea' },
                    { key: 'button_text', label: 'Button-Beschriftung', type: 'text' },
                    { key: 'button_url', label: 'Button-Ziel', type: 'text' },
                ],
            },
            fields: [
                { key: 'height', label: 'Höhe (% der Bildschirmhöhe)', type: 'number' },
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
                { key: 'url', label: 'Link-Ziel', type: 'text' },
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

    const presets = [
        { label: '1', spans: [12], title: '1 Spalte' },
        { label: '½ ½', spans: [6, 6], title: '2 gleiche Spalten' },
        { label: '⅓ ⅓ ⅓', spans: [4, 4, 4], title: '3 gleiche Spalten' },
        { label: '¼ ×4', spans: [3, 3, 3, 3], title: '4 gleiche Spalten' },
        { label: '⅔ ⅓', spans: [8, 4], title: 'Breit / Schmal' },
        { label: '⅓ ⅔', spans: [4, 8], title: 'Schmal / Breit' },
        { label: '¼ ½ ¼', spans: [3, 6, 3], title: 'Rand / Mitte / Rand' },
    ];

    const canvas = root.querySelector('.ed-canvas');
    const paletteWrap = root.querySelector('.ed-palette-items');
    const inspectorBody = root.querySelector('.ed-inspector-body');
    const presetBar = root.querySelector('.ed-presets');
    const saveBtn = document.getElementById('ed-save');
    const statusEl = document.getElementById('ed-status');

    let selected = null;   // { r, c, b }
    let dragData = null;   // {kind:'new',type} | {kind:'move',from} | {kind:'row',from:r}
    let dirty = false;

    /* ---------- Hilfsfunktionen ---------- */

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[ch]);

    function markDirty() {
        dirty = true;
        statusEl.textContent = 'Ungespeicherte Änderungen';
        statusEl.className = 'ed-status is-dirty';
    }

    function newBlock(type) {
        const data = JSON.parse(JSON.stringify(blockDefs[type].defaults));
        return { id: uid('b'), type: type, data: data };
    }

    function blockPreview(block) {
        const d = block.data || {};
        switch (block.type) {
            case 'heading': {
                const level = ['h1', 'h2', 'h3', 'h4'].includes(d.level) ? d.level : 'h2';
                return '<' + level + '>' + esc(d.text) + '</' + level + '>';
            }
            case 'text': return '<div class="pv-text">' + (d.html || '') + '</div>';
            case 'image': return d.src
                ? '<img src="' + esc(d.src) + '" alt="' + esc(d.alt) + '">'
                : '<div class="pv-placeholder">Bild – in den Eigenschaften wählen</div>';
            case 'gallery': {
                const imgs = (d.images || []).filter((i) => i.src);
                if (!imgs.length) return '<div class="pv-placeholder">Galerie – Bilder in den Eigenschaften hinzufügen</div>';
                let html = '<div class="pv-thumbs">';
                imgs.slice(0, 4).forEach((i) => { html += '<img src="' + esc(i.src) + '" alt="">'; });
                if (imgs.length > 4) html += '<span class="pv-more">+' + (imgs.length - 4) + '</span>';
                return html + '</div>';
            }
            case 'slider': {
                const imgs = (d.images || []).filter((i) => i.src);
                return imgs.length
                    ? '<div class="pv-slide"><img src="' + esc(imgs[0].src) + '" alt=""><span class="pv-badge">Slider · ' + imgs.length + ' Slides</span></div>'
                    : '<div class="pv-placeholder">Slider – Slides in den Eigenschaften hinzufügen</div>';
            }
            case 'hero': {
                const slides = (d.slides || []);
                if (!slides.length) return '<div class="pv-placeholder">Hero – Slides in den Eigenschaften hinzufügen</div>';
                const first = slides[0];
                return '<div class="pv-hero"' + (first.src ? ' style="background-image:url(\'' + esc(first.src) + '\')"' : '') + '>' +
                    '<span>' + esc(first.title || 'Hero') + '</span>' +
                    '<span class="pv-badge">volle Breite · ' + slides.length + ' Slide(s)</span></div>';
            }
            case 'button': return '<span class="pv-btn ' + (d.style === 'outline' || d.style === 'ghost' ? 'is-outline' : '') + '">' + esc(d.text) + '</span>';
            case 'video': return d.url
                ? '<div class="pv-placeholder">▶ Video: ' + esc(d.url.slice(0, 60)) + '</div>'
                : '<div class="pv-placeholder">Video – URL in den Eigenschaften setzen</div>';
            case 'quote': return '<blockquote class="pv-quote">' + esc((d.text || '…').slice(0, 100)) + (d.author ? '<br><small>— ' + esc(d.author) + '</small>' : '') + '</blockquote>';
            case 'accordion': {
                const items = d.items || [];
                if (!items.length) return '<div class="pv-placeholder">Akkordeon – Abschnitte in den Eigenschaften hinzufügen</div>';
                return '<div class="pv-list">' + items.map((i) => '<div>▸ ' + esc(i.title || 'Abschnitt') + '</div>').join('') + '</div>';
            }
            case 'news': return '<div class="pv-placeholder">❑ Zeigt die ' + (parseInt(d.count, 10) || 3) + ' neuesten News (' + esc(d.layout || 'cards') + ')</div>';
            case 'events': return '<div class="pv-placeholder">◷ Zeigt die nächsten ' + (parseInt(d.count, 10) || 3) + ' Events (' + esc(d.layout || 'cards') + ')</div>';
            case 'html': return '<code class="pv-code">' + esc((d.code || '').slice(0, 120)) + '</code>';
            case 'divider': return '<hr>';
            case 'spacer': return '<div class="pv-spacer">Abstand: ' + (parseInt(d.height, 10) || 0) + 'px</div>';
            default: return '';
        }
    }

    /* ---------- Rendern ---------- */

    function render() {
        canvas.innerHTML = '';

        if (!state.rows.length) {
            const hint = document.createElement('div');
            hint.className = 'ed-empty';
            hint.innerHTML = 'Noch keine Inhalte.<br>Füge oben mit den Spalten-Vorlagen eine Zeile hinzu.';
            canvas.appendChild(hint);
            return;
        }

        state.rows.forEach((row, r) => {
            const rowEl = document.createElement('div');
            rowEl.className = 'ed-row';
            bindRowDropzone(rowEl, r);

            const bar = document.createElement('div');
            bar.className = 'ed-row-bar';
            bar.innerHTML =
                '<span class="ed-row-label" draggable="true" title="Ziehen zum Verschieben">⠿ Zeile ' + (r + 1) + '</span>' +
                '<span class="ed-row-tools">' +
                '<button type="button" data-act="row-up" title="Nach oben">↑</button>' +
                '<button type="button" data-act="row-down" title="Nach unten">↓</button>' +
                '<button type="button" data-act="col-add" title="Spalte hinzufügen">+ Spalte</button>' +
                '<button type="button" data-act="row-del" class="danger" title="Zeile löschen">✕</button>' +
                '</span>';
            bar.addEventListener('click', (e) => {
                const act = e.target.dataset && e.target.dataset.act;
                if (act) rowAction(act, r);
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
                colBar.innerHTML =
                    '<button type="button" data-act="narrower" title="Schmaler">−</button>' +
                    '<span class="ed-col-width">' + col.span + '/12</span>' +
                    '<button type="button" data-act="wider" title="Breiter">+</button>' +
                    '<button type="button" data-act="col-del" class="danger" title="Spalte löschen">✕</button>';
                colBar.addEventListener('click', (e) => {
                    const act = e.target.dataset && e.target.dataset.act;
                    if (act) colAction(act, r, c);
                });
                colEl.appendChild(colBar);

                const blocksEl = document.createElement('div');
                blocksEl.className = 'ed-blocks';
                bindDropzone(blocksEl, r, c);

                col.blocks.forEach((block, b) => {
                    blocksEl.appendChild(renderBlock(block, r, c, b));
                });

                if (!col.blocks.length) {
                    const empty = document.createElement('div');
                    empty.className = 'ed-col-empty';
                    empty.textContent = 'Block hierher ziehen';
                    blocksEl.appendChild(empty);
                }

                colEl.appendChild(blocksEl);
                colsEl.appendChild(colEl);
            });

            rowEl.appendChild(colsEl);
            canvas.appendChild(rowEl);
        });
    }

    function renderBlock(block, r, c, b) {
        const def = blockDefs[block.type] || { label: block.type, icon: '?' };
        const el = document.createElement('div');
        el.className = 'ed-block';
        el.draggable = true;
        if (selected && selected.r === r && selected.c === c && selected.b === b) {
            el.classList.add('is-selected');
        }
        el.innerHTML =
            '<div class="ed-block-head"><span class="ed-block-icon">' + def.icon + '</span>' + esc(def.label) + '</div>' +
            '<div class="ed-block-preview">' + blockPreview(block) + '</div>';

        el.addEventListener('click', (e) => {
            e.stopPropagation();
            select(r, c, b);
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

    function select(r, c, b) {
        selected = { r: r, c: c, b: b };
        render();
        buildInspector();
    }

    function deselect() {
        selected = null;
        inspectorBody.innerHTML = '<p class="muted small">Klicke auf einen Block, um ihn zu bearbeiten.</p>';
    }

    function selectedBlock() {
        if (!selected) return null;
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
        } else {
            input = document.createElement('input');
            input.type = field.type === 'number' ? 'number' : 'text';
            input.value = value != null ? value : '';
        }
        input.addEventListener('input', () => {
            onChange(field.type === 'number' ? (parseInt(input.value, 10) || 0) : input.value);
        });
        return input;
    }

    function buildInspector() {
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
            const wrap = document.createElement('div');
            wrap.className = 'form-group';
            if (field.type !== 'checkbox') {
                const label = document.createElement('label');
                label.textContent = field.label;
                wrap.appendChild(label);
            }
            wrap.appendChild(fieldInput(field, block.data[field.key], (v) => {
                block.data[field.key] = v;
                markDirty();
                updateSelectedPreview();
            }));
            inspectorBody.appendChild(wrap);
        });

        if (!def.fields.length && !def.items) {
            const note = document.createElement('p');
            note.className = 'muted small';
            note.textContent = 'Dieser Block hat keine Einstellungen.';
            inspectorBody.appendChild(note);
        }

        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'btn btn-danger btn-block';
        del.textContent = 'Block löschen';
        del.addEventListener('click', () => {
            const s = selected;
            state.rows[s.r].columns[s.c].blocks.splice(s.b, 1);
            deselect();
            markDirty();
            render();
        });
        inspectorBody.appendChild(del);
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
                    const wrap = document.createElement('div');
                    wrap.className = 'form-group';
                    const label = document.createElement('label');
                    label.textContent = field.label;
                    wrap.appendChild(label);
                    wrap.appendChild(fieldInput(field, item[field.key], (v) => {
                        item[field.key] = v;
                        markDirty();
                        updateSelectedPreview();
                    }));
                    itemEl.appendChild(wrap);
                });
                list.appendChild(itemEl);
            });
        };

        const changed = () => {
            markDirty();
            updateSelectedPreview();
            rebuild();
        };

        const hasImage = spec.fields.some((f) => f.type === 'image');
        const add = document.createElement('button');
        add.type = 'button';
        add.className = 'btn btn-small btn-block';
        add.textContent = '+ ' + spec.itemLabel + ' hinzufügen';
        add.addEventListener('click', () => {
            if (hasImage) {
                window.AdminTools.openMediaPicker((url) => {
                    const item = {};
                    spec.fields.forEach((f) => { item[f.key] = ''; });
                    item[spec.fields.find((f) => f.type === 'image').key] = url;
                    items().push(item);
                    changed();
                });
            } else {
                const item = {};
                spec.fields.forEach((f) => { item[f.key] = ''; });
                items().push(item);
                changed();
            }
        });
        container.appendChild(add);

        rebuild();
        inspectorBody.appendChild(container);
    }

    // Nur die Vorschau des ausgewählten Blocks aktualisieren, damit die
    // Eingabefelder im Inspektor den Fokus behalten.
    function updateSelectedPreview() {
        const block = selectedBlock();
        const el = canvas.querySelector('.ed-block.is-selected .ed-block-preview');
        if (block && el) el.innerHTML = blockPreview(block);
    }

    /* ---------- Zeilen & Spalten ---------- */

    function addRow(spans) {
        state.rows.push({
            id: uid('row'),
            columns: spans.map((span) => ({ id: uid('col'), span: span, blocks: [] })),
        });
        markDirty();
        render();
        canvas.parentElement.scrollTop = canvas.parentElement.scrollHeight;
    }

    function rowAction(act, r) {
        const rows = state.rows;
        if (act === 'row-up' && r > 0) {
            [rows[r - 1], rows[r]] = [rows[r], rows[r - 1]];
        } else if (act === 'row-down' && r < rows.length - 1) {
            [rows[r], rows[r + 1]] = [rows[r + 1], rows[r]];
        } else if (act === 'row-del') {
            const hasContent = rows[r].columns.some((col) => col.blocks.length);
            if (hasContent && !confirm('Diese Zeile enthält Inhalte. Wirklich löschen?')) return;
            rows.splice(r, 1);
        } else if (act === 'col-add') {
            const used = rows[r].columns.reduce((sum, col) => sum + col.span, 0);
            rows[r].columns.push({ id: uid('col'), span: Math.min(Math.max(12 - used, 1), 12), blocks: [] });
        } else {
            return;
        }
        deselect();
        markDirty();
        render();
    }

    function colAction(act, r, c) {
        const cols = state.rows[r].columns;
        const col = cols[c];
        if (act === 'wider') {
            col.span = Math.min(12, col.span + 1);
        } else if (act === 'narrower') {
            col.span = Math.max(1, col.span - 1);
        } else if (act === 'col-del') {
            if (cols.length === 1) {
                alert('Die letzte Spalte einer Zeile kann nicht gelöscht werden. Lösche stattdessen die Zeile.');
                return;
            }
            if (col.blocks.length && !confirm('Diese Spalte enthält Inhalte. Wirklich löschen?')) return;
            cols.splice(c, 1);
        } else {
            return;
        }
        deselect();
        markDirty();
        render();
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
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            save();
        }
    });
    window.addEventListener('beforeunload', (e) => {
        if (dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    canvas.addEventListener('click', deselect);

    render();
})();
