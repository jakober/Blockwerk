/**
 * Seitenverwaltung: Seitenbaum per Drag & Drop umsortieren.
 * - Reihenfolge ändern (Geschwister vertauschen)
 * - Verschachteln: auf eine Seite ziehen -> Unterseite
 * - Ausrücken: an den linken Rand ziehen -> zurück zur Hauptseite
 * Speichert die neue Struktur per POST /admin/pages/reorder (CSRF im Header).
 */
(function () {
    'use strict';

    var tree = document.getElementById('page-tree');
    if (!tree) return;

    var statusEl = document.getElementById('pt-status');
    var dragItem = null;   // aktuell gezogenes <li.pt-item>
    var marker = null;     // Einfüge-Indikator

    function ensureMarker() {
        if (!marker) {
            marker = document.createElement('li');
            marker.className = 'pt-marker';
        }
        return marker;
    }

    function clearHints() {
        tree.querySelectorAll('.pt-row.is-inside').forEach(function (r) { r.classList.remove('is-inside'); });
        if (marker && marker.parentNode) marker.parentNode.removeChild(marker);
    }

    function setStatus(text, ok) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.className = 'pt-status small' + (ok === true ? ' is-ok' : ok === false ? ' is-err' : '');
    }

    // --- Drag-Start / Ende -------------------------------------------------

    tree.addEventListener('dragstart', function (e) {
        var row = e.target.closest('.pt-row');
        if (!row || !tree.contains(row)) return;
        dragItem = row.closest('.pt-item');
        dragItem.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        // Firefox verlangt gesetzte Daten, sonst startet kein Drag.
        try { e.dataTransfer.setData('text/plain', dragItem.dataset.id); } catch (err) {}
    });

    tree.addEventListener('dragend', function () {
        if (dragItem) dragItem.classList.remove('is-dragging');
        dragItem = null;
        clearHints();
    });

    // --- Dragover: Einfügeposition bestimmen -------------------------------

    tree.addEventListener('dragover', function (e) {
        if (!dragItem) return;
        // Innerhalb des Baums IMMER Drop zulassen – sonst feuert 'drop' nicht,
        // z. B. wenn der Zeiger genau über der Einfüge-Linie (Marker) oder in
        // einer Lücke zwischen Zeilen liegt. Das war der Grund, warum das
        // Ablegen "manchmal" nichts tat.
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        var row = e.target.closest('.pt-row');
        // Nicht über einer Zeile (z. B. über dem Marker): letzten Hinweis behalten.
        if (!row || !tree.contains(row)) return;
        var overItem = row.closest('.pt-item');
        // Nicht in sich selbst oder eigene Nachfahren ablegen – Hinweis behalten.
        if (overItem === dragItem || dragItem.contains(overItem)) return;

        clearHints();

        var rect = row.getBoundingClientRect();
        var offset = e.clientY - rect.top;
        var zoneTop = rect.height * 0.28;
        var zoneBottom = rect.height * 0.72;

        if (offset >= zoneTop && offset <= zoneBottom) {
            // Mittlerer Bereich -> als Unterseite (Kind) einsetzen.
            row.classList.add('is-inside');
        } else {
            // Oberer/unterer Bereich -> als Geschwister davor/danach.
            // Am linken Rand (kleiner Einzug) auf Wurzelebene erlauben.
            var m = ensureMarker();
            var parentList = overItem.parentNode; // <ul>
            if (offset < zoneTop) {
                parentList.insertBefore(m, overItem);
            } else {
                parentList.insertBefore(m, overItem.nextSibling);
            }
        }
    });

    // --- Drop: Verschieben durchführen und speichern -----------------------

    tree.addEventListener('drop', function (e) {
        if (!dragItem) return;
        e.preventDefault();

        var insideRow = tree.querySelector('.pt-row.is-inside');
        var moved = false;
        if (insideRow) {
            var parentItem = insideRow.closest('.pt-item');
            var childList = parentItem.querySelector(':scope > .pt-children');
            childList.appendChild(dragItem); // ans Ende der Unterseiten
            moved = true;
        } else if (marker && marker.parentNode) {
            marker.parentNode.insertBefore(dragItem, marker);
            moved = true;
        }
        clearHints();
        if (moved) save();
    });

    // --- Struktur serialisieren und speichern ------------------------------

    function collect() {
        var items = [];
        (function walk(list, parentId) {
            Array.prototype.forEach.call(list.children, function (li) {
                if (!li.classList || !li.classList.contains('pt-item')) return;
                items.push({ id: parseInt(li.dataset.id, 10), parent_id: parentId });
                var sub = li.querySelector(':scope > .pt-children');
                if (sub) walk(sub, parseInt(li.dataset.id, 10));
            });
        })(tree, null);
        return items;
    }

    function save() {
        setStatus('Speichere Reihenfolge …');
        fetch(tree.dataset.reorderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': tree.dataset.csrf },
            body: JSON.stringify({ items: collect() })
        }).then(function (r) {
            return r.ok ? r.json() : Promise.reject(r.status);
        }).then(function (res) {
            if (res && res.ok) {
                setStatus('✓ Reihenfolge gespeichert', true);
                setTimeout(function () { setStatus(''); }, 2500);
            } else {
                setStatus('Konnte nicht gespeichert werden.', false);
            }
        }).catch(function () {
            setStatus('Speichern fehlgeschlagen – bitte Seite neu laden.', false);
        });
    }
})();
