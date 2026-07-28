/**
 * Durchsuchbare Länder-Auswahl – reichert ein natives <select data-country-select>
 * an (mehrfach bei <select multiple>, sonst Einzelauswahl). Ohne JS bleibt das
 * native Select nutzbar. Keine Abhängigkeiten. Nutzt sowohl im Admin (Versand)
 * als auch in der Kasse.
 */
(function () {
    'use strict';

    if (!document.getElementById('csel-css')) {
        var st = document.createElement('style');
        st.id = 'csel-css';
        st.textContent = [
            '.csel{position:relative;font:inherit}',
            '.csel-control{display:flex;flex-wrap:wrap;gap:4px;align-items:center;min-height:40px;padding:5px 8px;border:1px solid #cbb8a0;border-radius:8px;background:#fff;cursor:text}',
            '.csel-control.is-open{border-color:#ea580c;box-shadow:0 0 0 3px rgba(234,88,12,.15)}',
            '.csel-chip{display:inline-flex;align-items:center;gap:5px;background:#fff1e6;border:1px solid #f0c9a8;border-radius:6px;padding:2px 4px 2px 7px;font-size:13px;color:#2b1d12}',
            '.csel-chip button{border:none;background:none;cursor:pointer;font-size:15px;line-height:1;color:#9a3412;padding:0}',
            '.csel-search{border:none;outline:none;flex:1 1 60px;min-width:60px;font:inherit;background:transparent;padding:2px;color:#2b1d12}',
            '.csel-single{color:#2b1d12}',
            '.csel-drop{position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:60;max-height:260px;overflow:auto;background:#fff;border:1px solid #cbb8a0;border-radius:8px;box-shadow:0 12px 30px rgba(0,0,0,.18);display:none}',
            '.csel-drop.is-open{display:block}',
            '.csel-opt{padding:8px 12px;cursor:pointer;font-size:14px;color:#2b1d12}',
            '.csel-opt:hover{background:#fff1e6}',
            '.csel-opt.is-sel{font-weight:600}',
            '.csel-opt.is-sel::after{content:" ✓";color:#16a34a}',
            '.csel-opt.is-hidden{display:none}',
            '.csel-empty{padding:8px 12px;color:#9a8a78;font-size:13px}'
        ].join('');
        document.head.appendChild(st);
    }

    function enhance(select) {
        if (select.dataset.cselDone) { return; }
        select.dataset.cselDone = '1';
        var multi = select.multiple;

        var wrap = document.createElement('div');
        wrap.className = 'csel';
        select.parentNode.insertBefore(wrap, select);
        select.style.display = 'none';
        wrap.appendChild(select);

        var control = document.createElement('div');
        control.className = 'csel-control';
        var search = document.createElement('input');
        search.type = 'text';
        search.className = 'csel-search';
        search.autocomplete = 'off';
        var drop = document.createElement('div');
        drop.className = 'csel-drop';
        control.appendChild(search);
        wrap.appendChild(control);
        wrap.appendChild(drop);

        var opts = Array.prototype.slice.call(select.options).filter(function (o) { return o.value !== '' || o.textContent.trim() !== ''; });

        function selected() { return opts.filter(function (o) { return o.selected; }); }

        function renderControl() {
            Array.prototype.slice.call(control.children).forEach(function (c) { if (c !== search) { control.removeChild(c); } });
            if (multi) {
                selected().forEach(function (o) {
                    var chip = document.createElement('span');
                    chip.className = 'csel-chip';
                    var t = document.createElement('span'); t.textContent = o.textContent; chip.appendChild(t);
                    var x = document.createElement('button'); x.type = 'button'; x.textContent = '×';
                    x.addEventListener('click', function (e) { e.stopPropagation(); o.selected = false; changed(); });
                    chip.appendChild(x);
                    control.insertBefore(chip, search);
                });
                search.placeholder = selected().length ? '' : (select.dataset.placeholder || 'Land hinzufügen …');
            } else {
                var sel = selected()[0];
                if (sel && document.activeElement !== search) {
                    var lbl = document.createElement('span'); lbl.className = 'csel-single'; lbl.textContent = sel.textContent;
                    control.insertBefore(lbl, search);
                    search.placeholder = '';
                } else {
                    search.placeholder = sel ? sel.textContent : (select.dataset.placeholder || 'Land wählen …');
                }
            }
        }

        function renderDrop() {
            drop.innerHTML = '';
            var term = search.value.trim().toLowerCase();
            var any = false;
            opts.forEach(function (o) {
                var row = document.createElement('div');
                row.className = 'csel-opt' + (o.selected ? ' is-sel' : '');
                row.textContent = o.textContent;
                if (o.textContent.toLowerCase().indexOf(term) === -1) { row.classList.add('is-hidden'); } else { any = true; }
                row.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    if (multi) { o.selected = !o.selected; } else { opts.forEach(function (x) { x.selected = false; }); o.selected = true; }
                    search.value = '';
                    changed();
                    if (multi) { renderDrop(); search.focus(); } else { close(); }
                });
                drop.appendChild(row);
            });
            if (!any) { var em = document.createElement('div'); em.className = 'csel-empty'; em.textContent = 'Kein Treffer'; drop.appendChild(em); }
        }

        function changed() {
            renderControl();
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        function open() { control.classList.add('is-open'); drop.classList.add('is-open'); renderDrop(); }
        function close() { control.classList.remove('is-open'); drop.classList.remove('is-open'); search.value = ''; renderControl(); }

        control.addEventListener('click', function () { open(); search.focus(); });
        search.addEventListener('input', renderDrop);
        search.addEventListener('focus', function () { open(); renderControl(); });
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { close(); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                var first = drop.querySelector('.csel-opt:not(.is-hidden)');
                if (first) { first.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true })); }
            } else if (e.key === 'Backspace' && multi && search.value === '') {
                var sel = selected();
                if (sel.length) { sel[sel.length - 1].selected = false; changed(); renderDrop(); }
            }
        });
        document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) { close(); } });

        renderControl();
    }

    function initAll(root) {
        (root || document).querySelectorAll('select[data-country-select]').forEach(enhance);
    }
    if (document.readyState !== 'loading') { initAll(); } else { document.addEventListener('DOMContentLoaded', function () { initAll(); }); }
    window.CountrySelect = { init: initAll };
})();
