/**
 * Drag-&-Drop-Inhalts-Editor.
 *
 * Struktur des Inhalts: state.rows[] → columns[] (span im 12er-Raster) → blocks[].
 * Neue Block-Typen: Eintrag in blockDefs ergänzen und serverseitig in
 * app/Core/BlockRegistry.php registrieren.
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

    const blockDefs = {
        heading: {
            label: 'Überschrift', icon: 'H',
            defaults: { text: 'Neue Überschrift', level: 'h2' },
            fields: [
                { key: 'text', label: 'Text', type: 'text' },
                { key: 'level', label: 'Größe', type: 'select', options: [['h1', 'H1 – sehr groß'], ['h2', 'H2 – groß'], ['h3', 'H3 – mittel'], ['h4', 'H4 – klein']] },
            ],
        },
        text: {
            label: 'Text', icon: '¶',
            defaults: { html: '<p>Neuer Textabsatz.</p>' },
            fields: [{ key: 'html', label: 'Inhalt (HTML erlaubt)', type: 'textarea' }],
        },
        image: {
            label: 'Bild', icon: '▣',
            defaults: { src: '', alt: '' },
            fields: [
                { key: 'src', label: 'Bild-URL', type: 'text' },
                { key: 'alt', label: 'Alternativtext', type: 'text' },
            ],
        },
        button: {
            label: 'Button', icon: '⏺',
            defaults: { text: 'Mehr erfahren', url: '#', style: 'primary' },
            fields: [
                { key: 'text', label: 'Beschriftung', type: 'text' },
                { key: 'url', label: 'Link-Ziel', type: 'text' },
                { key: 'style', label: 'Stil', type: 'select', options: [['primary', 'Primär'], ['outline', 'Outline']] },
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
    let dragData = null;   // { kind: 'new', type } | { kind: 'move', from: {r,c,b} }
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
        return { id: uid('b'), type: type, data: Object.assign({}, blockDefs[type].defaults) };
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
                : '<div class="pv-placeholder">Bild – URL in den Eigenschaften setzen</div>';
            case 'button': return '<span class="pv-btn ' + (d.style === 'outline' ? 'is-outline' : '') + '">' + esc(d.text) + '</span>';
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

            const bar = document.createElement('div');
            bar.className = 'ed-row-bar';
            bar.innerHTML =
                '<span class="ed-row-label">Zeile ' + (r + 1) + '</span>' +
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

    function buildInspector() {
        const block = selectedBlock();
        if (!block) { deselect(); return; }
        const def = blockDefs[block.type];
        inspectorBody.innerHTML = '';

        const heading = document.createElement('div');
        heading.className = 'ed-insp-type';
        heading.textContent = def.label;
        inspectorBody.appendChild(heading);

        def.fields.forEach((field) => {
            const wrap = document.createElement('div');
            wrap.className = 'form-group';
            const label = document.createElement('label');
            label.textContent = field.label;
            wrap.appendChild(label);

            let input;
            if (field.type === 'textarea') {
                input = document.createElement('textarea');
                input.rows = 8;
                input.className = 'code';
            } else if (field.type === 'select') {
                input = document.createElement('select');
                field.options.forEach(([value, text]) => {
                    const opt = document.createElement('option');
                    opt.value = value;
                    opt.textContent = text;
                    input.appendChild(opt);
                });
            } else {
                input = document.createElement('input');
                input.type = field.type === 'number' ? 'number' : 'text';
            }
            input.value = block.data[field.key] != null ? block.data[field.key] : '';
            input.addEventListener('input', () => {
                block.data[field.key] = field.type === 'number' ? parseInt(input.value, 10) || 0 : input.value;
                markDirty();
                updateSelectedPreview();
            });
            wrap.appendChild(input);
            inspectorBody.appendChild(wrap);
        });

        if (!def.fields.length) {
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

    /* ---------- Drag & Drop ---------- */

    function bindDropzone(zone, r, c) {
        zone.addEventListener('dragover', (e) => {
            if (!dragData) return;
            e.preventDefault();
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
            e.preventDefault();
            if (!dragData) return;
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
        canvas.querySelectorAll('.insert-before, .insert-after').forEach((el) => {
            el.classList.remove('insert-before', 'insert-after');
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
