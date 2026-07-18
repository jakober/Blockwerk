/**
 * Einheitliche Bestätigungs-/Hinweis-Dialoge im Backend (statt der nativen
 * alert()/confirm()-Fenster). Nutzt dieselbe Optik wie die übrigen Modale
 * (.modal-overlay/.modal).
 *
 *   AdminDialog.confirm('Wirklich löschen?', { danger: true }).then(ok => …)
 *   AdminDialog.alert('Gespeichert.')
 *
 * Zusätzlich automatisches Verdrahten: jedes <form data-confirm="Text"> und
 * jeder Link/Button mit data-confirm zeigt vor der Aktion den Dialog.
 * data-confirm-danger färbt den Bestätigen-Knopf rot.
 */
(function () {
    'use strict';

    function esc(value) {
        var d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML;
    }

    function open(opts) {
        return new Promise(function (resolve) {
            var overlay = document.createElement('div');
            overlay.className = 'modal-overlay ad-dialog';

            var modal = document.createElement('div');
            modal.className = 'modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');

            var html = '';
            if (opts.title) html += '<h3>' + esc(opts.title) + '</h3>';
            html += '<p class="ad-msg">' + esc(opts.message).replace(/\n/g, '<br>') + '</p>';
            html += '<div class="modal-actions">';
            if (opts.cancelText) html += '<button type="button" class="btn btn-ghost" data-ad="cancel">' + esc(opts.cancelText) + '</button>';
            html += '<button type="button" class="btn ' + (opts.danger ? 'btn-danger' : 'btn-primary') + '" data-ad="ok">' + esc(opts.confirmText || 'OK') + '</button>';
            html += '</div>';
            modal.innerHTML = html;
            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            var okBtn = modal.querySelector('[data-ad="ok"]');
            var cancelBtn = modal.querySelector('[data-ad="cancel"]');
            okBtn.focus();

            function done(val) {
                overlay.remove();
                document.removeEventListener('keydown', onKey);
                resolve(val);
            }
            function onKey(e) {
                if (e.key === 'Escape') { done(false); }
                else if (e.key === 'Enter') { e.preventDefault(); done(true); }
            }
            okBtn.addEventListener('click', function () { done(true); });
            if (cancelBtn) cancelBtn.addEventListener('click', function () { done(false); });
            overlay.addEventListener('click', function (e) { if (e.target === overlay) done(false); });
            document.addEventListener('keydown', onKey);
        });
    }

    window.AdminDialog = {
        confirm: function (message, opts) {
            opts = opts || {};
            return open({
                message: message,
                title: opts.title || 'Bestätigen',
                confirmText: opts.confirmText || 'OK',
                cancelText: opts.cancelText || 'Abbrechen',
                danger: !!opts.danger
            });
        },
        alert: function (message, opts) {
            opts = opts || {};
            return open({
                message: message,
                title: opts.title || 'Hinweis',
                confirmText: opts.confirmText || 'OK',
                cancelText: null
            });
        }
    };

    /* ---------- Automatisches Verdrahten von data-confirm ---------- */

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
        if (form.dataset.adConfirmed === '1') { form.dataset.adConfirmed = ''; return; }
        e.preventDefault();
        window.AdminDialog.confirm(form.getAttribute('data-confirm'), {
            danger: form.hasAttribute('data-confirm-danger'),
            confirmText: form.getAttribute('data-confirm-ok') || 'OK'
        }).then(function (ok) {
            if (!ok) return;
            form.dataset.adConfirmed = '1';
            if (form.requestSubmit) form.requestSubmit(); else form.submit();
        });
    }, true);

    document.addEventListener('click', function (e) {
        var el = e.target.closest('a[data-confirm], button[data-confirm]');
        if (!el || el.closest('form')) return; // Formular-Fälle laufen über submit
        if (el.dataset.adConfirmed === '1') { el.dataset.adConfirmed = ''; return; }
        e.preventDefault();
        window.AdminDialog.confirm(el.getAttribute('data-confirm'), {
            danger: el.hasAttribute('data-confirm-danger'),
            confirmText: el.getAttribute('data-confirm-ok') || 'OK'
        }).then(function (ok) {
            if (!ok) return;
            if (el.tagName === 'A') { window.location = el.href; }
            else { el.dataset.adConfirmed = '1'; el.click(); }
        });
    }, true);
})();
