/**
 * Kontextbezogene Backend-Hilfe: fester Button unten rechts, öffnet ein
 * kleines Chat-Fenster. Beim ersten Öffnen erklärt die KI automatisch, was man
 * auf der aktuellen Seite tun kann; danach kann man Fragen dazu stellen.
 */
(function () {
    'use strict';
    var cfg = window.__aiHelp;
    if (!cfg) { return; }
    var btn = document.getElementById('ai-help-btn');
    var panel = document.getElementById('ai-help-panel');
    var msgs = document.getElementById('ai-help-msgs');
    var form = document.getElementById('ai-help-form');
    var input = document.getElementById('ai-help-input');
    var closeBtn = document.getElementById('ai-help-close');
    if (!btn || !panel) { return; }

    var history = [];
    var busy = false;
    var opened = false;

    function bubble(role, text) {
        var d = document.createElement('div');
        d.className = 'ai-help-msg is-' + role;
        d.textContent = text;
        msgs.appendChild(d);
        msgs.scrollTop = msgs.scrollHeight;
        return d;
    }

    function ask(question) {
        if (busy) { return; }
        busy = true;
        if (question) { bubble('user', question); history.push({ role: 'user', text: question }); }
        var loading = bubble('assistant', '…');
        loading.classList.add('is-loading');
        fetch(cfg.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf },
            body: JSON.stringify({ page: cfg.page, title: cfg.title, question: question || '', messages: history })
        }).then(function (r) { return r.json(); }).then(function (res) {
            loading.remove();
            bubble('assistant', res.ok ? res.text : ('⚠️ ' + (res.error || 'Fehler.')));
            if (res.ok) { history.push({ role: 'assistant', text: res.text }); }
            busy = false;
            setTimeout(function () { input.focus(); }, 30);
        }).catch(function () {
            loading.remove();
            bubble('assistant', '⚠️ Verbindung fehlgeschlagen.');
            busy = false;
        });
    }

    function open() {
        panel.hidden = false;
        btn.classList.add('is-open');
        if (!opened) { opened = true; ask(''); }
        setTimeout(function () { input.focus(); }, 50);
    }
    function close() { panel.hidden = true; btn.classList.remove('is-open'); }

    btn.addEventListener('click', function () { if (panel.hidden) { open(); } else { close(); } });
    if (closeBtn) { closeBtn.addEventListener('click', close); }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var q = input.value.trim();
        if (!q) { return; }
        input.value = '';
        ask(q);
    });
})();
