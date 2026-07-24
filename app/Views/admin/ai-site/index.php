<?php /** @var array $history @var array $files @var array $images */ ?>
<div class="aisite">
    <h1 style="margin:0 0 4px">KI-Webseite</h1>
    <p class="muted" style="margin:0 0 18px">Beschreibe, was die KI tun soll – sie baut die Website als HTML/CSS/jQuery. Änderungswünsche einfach als Folgeauftrag schreiben; die KI weiß, was bereits existiert.</p>

    <div class="card">
        <div id="ai-log" class="ai-log">
            <?php foreach ($history as $turn): ?>
                <div class="ai-msg ai-<?= $turn['role'] === 'assistant' ? 'bot' : 'user' ?>"><?= nl2br(e((string) $turn['text'])) ?></div>
            <?php endforeach; ?>
        </div>
        <form id="ai-form" class="ai-input">
            <textarea id="ai-text" rows="3" placeholder="z. B. Erstelle eine moderne Startseite für ein Café mit Hero, Menü-Sektion und Kontaktbereich."></textarea>
            <div class="ai-actions">
                <label class="btn btn-ghost btn-small" style="cursor:pointer">🖼 Bild hochladen<input type="file" id="ai-upload" accept="image/*" hidden></label>
                <span id="ai-balance" class="muted small"></span>
                <span class="spacer" style="margin-left:auto"></span>
                <button type="submit" id="ai-send" class="btn btn-primary">Senden</button>
            </div>
        </form>
    </div>

    <div class="editor-grid" style="margin-top:16px">
        <div class="card">
            <h3 style="margin-top:0">Dateien der Website</h3>
            <ul id="ai-files" class="ai-files">
                <?php foreach ($files as $f): ?><li><?= e($f['path']) ?></li><?php endforeach; ?>
                <?php if (empty($files)): ?><li class="muted small">Noch keine Dateien.</li><?php endif; ?>
            </ul>
        </div>
        <aside class="card">
            <h3 style="margin-top:0">Bilder</h3>
            <div id="ai-images" class="ai-images">
                <?php foreach ($images as $i): ?><a href="<?= e($i['url']) ?>" target="_blank" rel="noopener"><img src="<?= e($i['url']) ?>" alt=""></a><?php endforeach; ?>
                <?php if (empty($images)): ?><p class="muted small">Noch keine Bilder.</p><?php endif; ?>
            </div>
        </aside>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px">
        <form method="post" action="<?= e(url('/admin/ai-site/clear')) ?>" class="inline" data-confirm="Gesprächsverlauf löschen? Deine Website-Dateien bleiben erhalten." data-confirm-ok="Verlauf löschen">
            <?= csrf_field() ?><button type="submit" class="btn btn-ghost btn-small">Verlauf löschen</button>
        </form>
        <a class="btn btn-ghost btn-small" href="<?= e(url('/admin/ai-site/setup-cms')) ?>">Zum CMS-Modus wechseln →</a>
    </div>
</div>

<style>
    .ai-log { display:flex; flex-direction:column; gap:10px; max-height:46vh; overflow-y:auto; margin-bottom:12px; }
    .ai-log:empty { display:none; }
    .ai-msg { padding:10px 13px; border-radius:12px; font-size:14px; line-height:1.55; max-width:88%; white-space:pre-wrap; }
    .ai-user { align-self:flex-end; background:var(--primary); color:#fff; border-bottom-right-radius:4px; }
    .ai-bot { align-self:flex-start; background:var(--bg); border:1px solid var(--border); border-bottom-left-radius:4px; }
    .ai-input textarea { width:100%; resize:vertical; padding:11px 13px; border:1px solid var(--border); border-radius:10px; font:inherit; }
    .ai-actions { display:flex; align-items:center; gap:8px; margin-top:8px; }
    .ai-files { list-style:none; margin:0; padding:0; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:13px; }
    .ai-files li { padding:4px 0; border-bottom:1px solid var(--border); }
    .ai-images { display:grid; grid-template-columns:repeat(auto-fill,minmax(70px,1fr)); gap:6px; }
    .ai-images img { width:100%; height:64px; object-fit:cover; border-radius:8px; display:block; }
    .ai-msg.is-loading::after { content:'…'; }
</style>

<script src="<?= e(asset('/assets/js/admin-dialog.js')) ?>" defer></script>
<script>
(function () {
    var base = window.CMS_BASE || '';
    var form = document.getElementById('ai-form');
    var text = document.getElementById('ai-text');
    var log = document.getElementById('ai-log');
    var sendBtn = document.getElementById('ai-send');
    var balanceEl = document.getElementById('ai-balance');
    var filesEl = document.getElementById('ai-files');

    function esc(s) { return String(s).replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }); }
    function addMsg(role, txt) {
        var d = document.createElement('div');
        d.className = 'ai-msg ai-' + (role === 'assistant' ? 'bot' : 'user');
        d.innerHTML = esc(txt).replace(/\n/g, '<br>');
        log.appendChild(d);
        log.scrollTop = log.scrollHeight;
        return d;
    }
    function setBalance(b) { if (b != null) balanceEl.textContent = 'Guthaben: ' + Number(b).toLocaleString('de-DE') + ' Token'; }
    function renderFiles(files) {
        if (!files) return;
        filesEl.innerHTML = files.length ? files.map(function (f) { return '<li>' + esc(f.path) + '</li>'; }).join('') : '<li class="muted small">Noch keine Dateien.</li>';
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var msg = text.value.trim();
        if (!msg) return;
        addMsg('user', msg);
        text.value = '';
        sendBtn.disabled = true;
        var pending = addMsg('assistant', 'Die KI arbeitet');
        pending.classList.add('is-loading');
        fetch(base + '/admin/ai-site/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF },
            body: JSON.stringify({ message: msg })
        }).then(function (r) { return r.json(); }).then(function (j) {
            pending.classList.remove('is-loading');
            if (j.ok) {
                pending.innerHTML = esc(j.text).replace(/\n/g, '<br>');
                setBalance(j.balance);
                renderFiles(j.files);
            } else {
                pending.innerHTML = '<span style="color:#b91c1c">Fehler: ' + esc(j.error || 'unbekannt') + '</span>';
            }
        }).catch(function (err) {
            pending.classList.remove('is-loading');
            pending.innerHTML = '<span style="color:#b91c1c">Verbindungsfehler: ' + esc(err.message) + '</span>';
        }).finally(function () { sendBtn.disabled = false; });
    });

    document.getElementById('ai-upload').addEventListener('change', function () {
        if (!this.files || !this.files[0]) return;
        var fd = new FormData();
        fd.append('file', this.files[0]);
        fd.append('_csrf', window.CSRF);
        var input = this;
        fetch(base + '/admin/ai-site/upload', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (j) {
            if (j.ok) { addMsg('assistant', 'Bild hochgeladen: ' + j.url + '\nDu kannst der KI jetzt sagen, wo sie es einsetzen soll.'); }
            else { addMsg('assistant', 'Upload fehlgeschlagen: ' + (j.error || '')); }
            input.value = '';
        });
    });
})();
</script>
