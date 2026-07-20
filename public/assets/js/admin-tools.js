/**
 * Geteilte Admin-Werkzeuge:
 *   AdminTools.openMediaPicker(cb)  – Medien-Auswahldialog, ruft cb(url) auf
 *   AdminTools.richtext(textarea)   – macht aus einer Textarea einen
 *                                     Rich-Text-Editor mit Toolbar
 *
 * Elemente mit [data-media-pick="#selector"] öffnen den Dialog und schreiben
 * die URL in das Ziel-Input; Textareas mit [data-richtext] werden automatisch
 * zu Rich-Text-Editoren.
 */
(function () {
    'use strict';

    const BASE = window.CMS_BASE || '';

    /* ---------- Medien-Auswahldialog ---------- */

    function openMediaPicker(onSelect) {
        const overlay = document.createElement('div');
        overlay.className = 'mp-overlay';
        overlay.innerHTML =
            '<div class="mp-dialog">' +
            '<div class="mp-head"><strong>Mediathek</strong>' +
            '<a class="btn btn-small btn-ghost" href="' + BASE + '/admin/media" target="_blank" rel="noopener">Verwalten ↗</a>' +
            '<button type="button" class="mp-close" aria-label="Schließen">✕</button></div>' +
            '<div class="mp-body"><p class="muted">Lade…</p></div>' +
            '</div>';
        document.body.appendChild(overlay);

        const close = () => overlay.remove();
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay || e.target.closest('.mp-close')) close();
        });
        document.addEventListener('keydown', function esc(e) {
            if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
        });

        fetch(BASE + '/admin/media/list', { headers: { Accept: 'application/json' } })
            .then((res) => res.json())
            .then((json) => {
                const body = overlay.querySelector('.mp-body');
                const images = (json.items || []).filter((item) => item.isImage);
                if (!images.length) {
                    body.innerHTML = '<p class="muted">Noch keine Bilder in der Mediathek. ' +
                        '<a href="' + BASE + '/admin/media" target="_blank" rel="noopener">Jetzt hochladen ↗</a></p>';
                    return;
                }
                body.innerHTML = '';

                // Suche + Ordner-Filter
                const bar = document.createElement('div');
                bar.className = 'mp-toolbar';
                const search = document.createElement('input');
                search.type = 'search';
                search.placeholder = '🔍 Bilder durchsuchen …';
                bar.appendChild(search);
                const folders = (json.folders || []);
                let folderSelect = null;
                if (folders.length) {
                    folderSelect = document.createElement('select');
                    folderSelect.innerHTML = '<option value="">Alle Ordner</option>' +
                        folders.map((name) => '<option>' + name.replace(/[&<>"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])) + '</option>').join('');
                    bar.appendChild(folderSelect);
                }
                body.appendChild(bar);

                const grid = document.createElement('div');
                grid.className = 'mp-grid';
                images.forEach((item) => {
                    const cell = document.createElement('button');
                    cell.type = 'button';
                    cell.className = 'mp-item';
                    cell.title = item.name + (item.alt ? ' – ' + item.alt : '');
                    cell.dataset.search = (item.name + ' ' + (item.alt || '') + ' ' + (item.title || '')).toLowerCase();
                    cell.dataset.folder = item.folder || '';
                    cell.innerHTML = '<img src="' + (item.thumb || item.url) + '" alt="" loading="lazy"><span>' + item.name + '</span>';
                    cell.addEventListener('click', () => { onSelect(item.url, item); close(); });
                    grid.appendChild(cell);
                });
                body.appendChild(grid);

                const applyFilter = () => {
                    const q = search.value.trim().toLowerCase();
                    const folder = folderSelect ? folderSelect.value : '';
                    grid.querySelectorAll('.mp-item').forEach((cell) => {
                        cell.hidden = (q !== '' && !cell.dataset.search.includes(q))
                            || (folder !== '' && cell.dataset.folder !== folder);
                    });
                };
                search.addEventListener('input', applyFilter);
                if (folderSelect) folderSelect.addEventListener('change', applyFilter);
                setTimeout(() => search.focus(), 40);
            })
            .catch(() => {
                overlay.querySelector('.mp-body').innerHTML = '<p class="muted">Mediathek konnte nicht geladen werden.</p>';
            });
    }

    /* ---------- Seiten-Auswahldialog (für Link-Ziele) ---------- */

    function escHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    }

    function openPagePicker(onSelect) {
        const overlay = document.createElement('div');
        overlay.className = 'mp-overlay';
        overlay.innerHTML =
            '<div class="mp-dialog mp-dialog-narrow">' +
            '<div class="mp-head"><strong>Seite wählen</strong>' +
            '<button type="button" class="mp-close" aria-label="Schließen">✕</button></div>' +
            '<div class="mp-body"><p class="muted">Lade…</p></div>' +
            '</div>';
        document.body.appendChild(overlay);

        const close = () => overlay.remove();
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay || e.target.closest('.mp-close')) close();
        });
        document.addEventListener('keydown', function esc(e) {
            if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
        });

        fetch(BASE + '/admin/pages/link-list', { headers: { Accept: 'application/json' } })
            .then((res) => res.json())
            .then((json) => {
                const body = overlay.querySelector('.mp-body');
                const pages = json.pages || [];
                if (!pages.length) {
                    body.innerHTML = '<p class="muted">Es gibt noch keine Seiten zum Verlinken.</p>';
                    return;
                }
                body.innerHTML = '';
                const search = document.createElement('input');
                search.type = 'search';
                search.className = 'pp-search';
                search.placeholder = '🔍 Seite suchen …';
                body.appendChild(search);

                const list = document.createElement('div');
                list.className = 'pp-list';
                pages.forEach((p) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'pp-item';
                    btn.dataset.search = ((p.title || '') + ' ' + (p.slug || '')).toLowerCase();
                    btn.style.paddingLeft = (12 + (p.depth || 0) * 16) + 'px';
                    btn.innerHTML = '<span class="pp-title">' + escHtml(p.title) + '</span>' +
                        '<span class="pp-url">' + escHtml(p.url) + '</span>';
                    btn.addEventListener('click', () => { onSelect(p.url, p); close(); });
                    list.appendChild(btn);
                });
                body.appendChild(list);

                search.addEventListener('input', () => {
                    const q = search.value.trim().toLowerCase();
                    list.querySelectorAll('.pp-item').forEach((it) => {
                        it.hidden = q !== '' && !it.dataset.search.includes(q);
                    });
                });
                setTimeout(() => search.focus(), 40);
            })
            .catch(() => {
                overlay.querySelector('.mp-body').innerHTML = '<p class="muted">Seiten konnten nicht geladen werden.</p>';
            });
    }

    /* Kleiner Dialog „Link einfügen“ mit URL-Feld + Seiten-Auswahl. */
    function openLinkPrompt(initial, onOk) {
        const overlay = document.createElement('div');
        overlay.className = 'mp-overlay';
        overlay.innerHTML =
            '<div class="mp-dialog mp-dialog-narrow">' +
            '<div class="mp-head"><strong>Link einfügen</strong>' +
            '<button type="button" class="mp-close" aria-label="Schließen">✕</button></div>' +
            '<div class="mp-body">' +
            '<div class="form-group"><label>Link-Adresse</label>' +
            '<div class="lp-row"><input type="text" class="lp-url" placeholder="https://… oder Seite wählen">' +
            '<button type="button" class="btn btn-small lp-pick">Seite wählen</button></div></div>' +
            '<div class="lp-actions"><button type="button" class="btn btn-primary lp-ok">Einfügen</button></div>' +
            '</div></div>';
        document.body.appendChild(overlay);

        const urlInput = overlay.querySelector('.lp-url');
        urlInput.value = initial || '';
        const close = () => overlay.remove();
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay || e.target.closest('.mp-close')) close();
        });
        overlay.querySelector('.lp-pick').addEventListener('click', () => {
            openPagePicker((url) => { urlInput.value = url; urlInput.focus(); });
        });
        const ok = () => { const v = urlInput.value.trim(); close(); if (v) onOk(v); };
        overlay.querySelector('.lp-ok').addEventListener('click', ok);
        urlInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); ok(); } });
        setTimeout(() => urlInput.focus(), 40);
    }

    /* ---------- Rich-Text-Editor ---------- */

    const RT_BUTTONS = [
        ['bold', 'F', 'Fett'],
        ['italic', 'K', 'Kursiv'],
        ['underline', 'U', 'Unterstrichen'],
        ['|'],
        ['formatBlock:p', '¶', 'Absatz'],
        ['formatBlock:h2', 'H2', 'Überschrift 2'],
        ['formatBlock:h3', 'H3', 'Überschrift 3'],
        ['|'],
        ['insertUnorderedList', '•', 'Aufzählung'],
        ['insertOrderedList', '1.', 'Nummerierung'],
        ['|'],
        ['link', '🔗', 'Link einfügen'],
        ['removeFormat', '⌫', 'Formatierung entfernen'],
    ];

    function richtext(textarea) {
        if (textarea.dataset.rtReady) return;
        textarea.dataset.rtReady = '1';

        const wrap = document.createElement('div');
        wrap.className = 'rt-wrap';
        const toolbar = document.createElement('div');
        toolbar.className = 'rt-toolbar';
        const area = document.createElement('div');
        area.className = 'rt-area';
        area.contentEditable = 'true';
        area.innerHTML = textarea.value;

        RT_BUTTONS.forEach(([cmd, label, title]) => {
            if (cmd === '|') {
                const sep = document.createElement('span');
                sep.className = 'rt-sep';
                toolbar.appendChild(sep);
                return;
            }
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = label;
            btn.title = title;
            btn.addEventListener('mousedown', (e) => e.preventDefault()); // Auswahl behalten
            btn.addEventListener('click', () => {
                if (cmd === 'link') {
                    // Auswahl sichern – der Dialog nimmt den Fokus und würde sie
                    // sonst verlieren; vor createLink wiederherstellen.
                    const sel = window.getSelection();
                    const savedRange = sel && sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
                    openLinkPrompt('', (url) => {
                        area.focus();
                        if (savedRange) {
                            const s = window.getSelection();
                            s.removeAllRanges();
                            s.addRange(savedRange);
                        }
                        document.execCommand('createLink', false, url);
                        sync();
                    });
                    return;
                } else if (cmd.startsWith('formatBlock:')) {
                    document.execCommand('formatBlock', false, '<' + cmd.split(':')[1] + '>');
                } else {
                    document.execCommand(cmd, false);
                }
                area.focus();
                sync();
            });
            toolbar.appendChild(btn);
        });

        const sync = () => {
            textarea.value = area.innerHTML;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        };
        area.addEventListener('input', sync);
        area.addEventListener('blur', sync);

        wrap.appendChild(toolbar);
        wrap.appendChild(area);
        textarea.style.display = 'none';
        textarea.after(wrap);
    }

    /* ---------- Automatische Initialisierung ---------- */

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-media-pick]');
        if (!btn) return;
        const target = document.querySelector(btn.dataset.mediaPick);
        if (!target) return;
        openMediaPicker((url) => {
            target.value = url;
            target.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    document.querySelectorAll('textarea[data-richtext]').forEach(richtext);

    // Elemente mit [data-page-pick="#selector"] öffnen den Seitenwähler und
    // schreiben die gewählte Seiten-URL in das Ziel-Feld (für Link-Felder in
    // Formularen außerhalb des Editors).
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-page-pick]');
        if (!btn) return;
        const target = document.querySelector(btn.dataset.pagePick);
        if (!target) return;
        openPagePicker((url) => {
            target.value = url;
            target.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    window.AdminTools = {
        openMediaPicker: openMediaPicker,
        openPagePicker: openPagePicker,
        openLinkPrompt: openLinkPrompt,
        richtext: richtext,
    };
})();
