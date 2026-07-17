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
                const grid = document.createElement('div');
                grid.className = 'mp-grid';
                images.forEach((item) => {
                    const cell = document.createElement('button');
                    cell.type = 'button';
                    cell.className = 'mp-item';
                    cell.title = item.name;
                    cell.innerHTML = '<img src="' + (item.thumb || item.url) + '" alt="" loading="lazy"><span>' + item.name + '</span>';
                    cell.addEventListener('click', () => { onSelect(item.url); close(); });
                    grid.appendChild(cell);
                });
                body.appendChild(grid);
            })
            .catch(() => {
                overlay.querySelector('.mp-body').innerHTML = '<p class="muted">Mediathek konnte nicht geladen werden.</p>';
            });
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
                    const url = prompt('Link-Adresse:', 'https://');
                    if (url) document.execCommand('createLink', false, url);
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

    window.AdminTools = { openMediaPicker: openMediaPicker, richtext: richtext };
})();
