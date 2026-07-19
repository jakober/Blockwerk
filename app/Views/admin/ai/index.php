<?php if (!$configured): ?>
    <div class="card narrow ai-setup">
        <div class="ai-badge">✨</div>
        <h2>KI-Assistent einrichten</h2>
        <p class="muted">Der KI-Assistent erstellt komplette Seiten mit Texten, Bildern und modernem Design – du beschreibst einfach, was du brauchst.
        Zum Aktivieren brauchst du einen <strong>Lizenzschlüssel</strong> mit Token-Guthaben.</p>
        <a class="btn btn-primary" href="<?= e(url('/admin/settings')) ?>#ki">Zu den Einstellungen</a>
    </div>
<?php else: ?>

<div class="ai-wrap">
    <div class="ai-head">
        <div><span class="ai-badge">✨</span> <strong>KI-Assistent</strong>
            <span class="muted small">Beschreibe, was erstellt werden soll – Seiten, Texte und Bilder entstehen direkt im CMS.</span>
        </div>
        <div class="ai-head-right">
            <div class="ai-balance" id="ai-balance" title="Verbleibendes Token-Guthaben">
                <?php if ($balance !== null): ?>
                    Guthaben: <strong><?= number_format((int) $balance, 0, ',', '.') ?></strong> Tokens
                <?php elseif ($balanceError !== null): ?>
                    <span class="ai-balance-error"><?= e($balanceError) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($history)): ?>
            <form method="post" action="<?= e(url('/admin/ai/clear')) ?>" class="inline" data-confirm="Gesprächsverlauf wirklich löschen? Die KI vergisst dann den bisherigen Kontext." data-confirm-ok="Löschen" data-confirm-danger>
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-small btn-ghost" title="Verlauf löschen – KI beginnt von vorne">🗑 Neues Gespräch</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card ai-chat" id="ai-chat">
        <div class="ai-messages" id="ai-messages">
            <div class="ai-msg is-assistant">
                <div class="ai-msg-bubble">Hallo! Ich kenne dein Blockwerk Orange in- und auswendig. Sag mir zum Beispiel:<br>
                „<em>Erstelle eine Startseite für ein Café mit Hero-Bild, drei Vorteilen und Kontaktformular</em>“ –
                ich lege die Seite komplett an, inklusive Texten und generierten Bildern. Ich kann auch eine
                Webseite als Vorlage ansehen und Bilder herunterladen.</div>
            </div>
            <?php foreach (($history ?? []) as $m): ?>
                <div class="ai-msg is-<?= $m['role'] === 'assistant' ? 'assistant' : 'user' ?>">
                    <div class="ai-msg-bubble"><?= e($m['content']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="ai-copyright-note">⚠️ Wenn ich fremde Webseiten als Vorlage nutze oder Bilder herunterlade, können diese <strong>urheberrechtlich geschützt</strong> sein. Ich formuliere Texte selbst um – die Verantwortung für die Verwendung fremder Inhalte und Bilder liegt jedoch bei dir.</p>
        <div class="ai-status" id="ai-status" hidden>
            <span class="ai-spinner"></span><span id="ai-status-text">Die KI arbeitet …</span>
        </div>
        <form class="ai-inputbar" id="ai-form">
            <textarea id="ai-input" rows="2" placeholder="Was soll ich für dich erstellen oder ändern?"></textarea>
            <button type="submit" class="btn btn-primary" id="ai-send">Senden</button>
        </form>
    </div>
    <p class="muted small">Tipp: Die KI kann Seiten erstellen (<em>„Erstelle eine Seite über …“</em>), bestehende ändern (<em>„Mach die Überschrift auf der Seite X knackiger“</em>) und Bilder generieren. Jede Anfrage verbraucht Token-Guthaben<?= $buyUrl !== '' ? ' – <a href="' . e($buyUrl) . '" target="_blank" rel="noopener">Guthaben aufladen ↗</a>' : '' ?>.</p>
</div>

<script>
(function () {
    const messagesEl = document.getElementById('ai-messages');
    const form = document.getElementById('ai-form');
    const input = document.getElementById('ai-input');
    const send = document.getElementById('ai-send');
    const status = document.getElementById('ai-status');
    const statusText = document.getElementById('ai-status-text');
    const balanceEl = document.getElementById('ai-balance');
    const csrf = <?= json_encode(csrf_token()) ?>;
    const chatUrl = <?= json_encode(url('/admin/ai/chat')) ?>;
    const planUrl = <?= json_encode(url('/admin/ai/plan')) ?>;
    // Gespeicherten Verlauf laden, damit die KI sich an frühere Anweisungen erinnert.
    const history = <?= json_encode(array_map(static fn ($m) => ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'text' => $m['content']], $history ?? []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    let busy = false;

    const statusLines = [
        'Die KI plant die Inhalte …',
        'Texte werden geschrieben …',
        'Bilder werden generiert (kann etwas dauern) …',
        'Die Seite wird im CMS angelegt …',
    ];
    let statusTimer = null;

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        sendMessage();
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    function showStatus(msg) {
        const lines = msg ? [msg] : statusLines;
        let i = 0;
        status.hidden = false;
        statusText.textContent = lines[0];
        clearInterval(statusTimer);
        statusTimer = setInterval(() => { i = (i + 1) % lines.length; statusText.textContent = lines[i]; }, 6000);
    }
    function hideStatus() { status.hidden = true; clearInterval(statusTimer); }

    function updateBalance(bal) {
        if (bal !== null && bal !== undefined) {
            balanceEl.innerHTML = 'Guthaben: <strong>' + new Intl.NumberFormat('de-DE').format(bal) + '</strong> Tokens';
        }
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(body),
        }).then((r) => r.json());
    }

    function renderChecklist(steps) {
        const wrap = document.createElement('div');
        wrap.className = 'ai-msg is-assistant';
        const plan = document.createElement('div');
        plan.className = 'ai-plan';
        plan.innerHTML = '<div class="ai-plan-head">📋 Plan – wird Schritt für Schritt umgesetzt</div>';
        const ol = document.createElement('ol');
        ol.className = 'ai-plan-steps';
        const handles = steps.map((s, i) => {
            const li = document.createElement('li');
            li.className = 'ai-step is-pending';
            li.innerHTML = '<span class="ai-step-icon">○</span><div class="ai-step-main">'
                + '<div class="ai-step-title"></div><div class="ai-step-detail muted small"></div>'
                + '<div class="ai-step-result"></div></div>';
            li.querySelector('.ai-step-title').textContent = (i + 1) + '. ' + s.title;
            if (s.fast) {
                var f = document.createElement('span');
                f.className = 'ai-step-fast'; f.textContent = '⚡'; f.title = 'schnelles Modell';
                li.querySelector('.ai-step-title').append(' ', f);
            }
            li.querySelector('.ai-step-detail').textContent = s.detail || '';
            ol.appendChild(li);
            return { li: li, icon: li.querySelector('.ai-step-icon'), result: li.querySelector('.ai-step-result') };
        });
        plan.appendChild(ol);
        wrap.appendChild(plan);
        messagesEl.appendChild(wrap);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        return handles;
    }

    function setStep(h, state) {
        h.li.className = 'ai-step is-' + state;
        if (state === 'running') { h.icon.innerHTML = '<span class="ai-spinner"></span>'; }
        else if (state === 'done') { h.icon.textContent = '✓'; }
        else if (state === 'error') { h.icon.textContent = '✕'; }
        else { h.icon.textContent = '○'; }
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    async function runStep(steps, i) {
        const s = steps[i];
        const stepMsg = 'Setze jetzt NUR diesen Schritt aus dem Plan um: ' + (i + 1) + '. ' + s.title
            + (s.detail ? ' – ' + s.detail : '') + '. Fokussiere dich ausschließlich auf diesen einen Schritt.';
        const res = await postJson(chatUrl, { messages: history.concat([{ role: 'user', text: stepMsg }]), fast: !!s.fast });
        updateBalance(res.balance);
        if (res.ok) {
            history.push({ role: 'user', text: stepMsg });
            history.push({ role: 'assistant', text: res.text });
        }
        return res;
    }

    function stepControls(steps, i, handles) {
        const box = document.createElement('div');
        box.className = 'ai-step-controls';
        const retry = document.createElement('button');
        retry.type = 'button'; retry.className = 'btn btn-small'; retry.textContent = 'Schritt erneut versuchen';
        retry.addEventListener('click', () => { if (busy) return; busy = true; send.disabled = true; box.remove(); handles[i].result.innerHTML = ''; runFrom(steps, i, handles); });
        const skip = document.createElement('button');
        skip.type = 'button'; skip.className = 'btn btn-small btn-ghost'; skip.textContent = 'Überspringen & weiter';
        skip.addEventListener('click', () => { if (busy) return; busy = true; send.disabled = true; box.remove(); runFrom(steps, i + 1, handles); });
        box.append(retry, ' ', skip);
        handles[i].result.appendChild(box);
    }

    async function runFrom(steps, start, handles) {
        for (let i = start; i < steps.length; i++) {
            setStep(handles[i], 'running');
            showStatus(null);
            let res;
            try { res = await runStep(steps, i); }
            catch (e) { res = { ok: false, error: 'Verbindung fehlgeschlagen.' }; }
            if (res.ok) {
                setStep(handles[i], 'done');
                if (res.text && res.text !== 'Erledigt.') {
                    const t = document.createElement('div');
                    t.className = 'ai-step-text'; t.textContent = res.text;
                    handles[i].result.appendChild(t);
                }
                renderActions(handles[i].result, res.actions || []);
            } else {
                setStep(handles[i], 'error');
                const err = document.createElement('div');
                err.className = 'ai-step-err'; err.textContent = '⚠️ ' + (res.error || 'Fehler in diesem Schritt.');
                handles[i].result.appendChild(err);
                stepControls(steps, i, handles);
                hideStatus();
                done();
                return;
            }
        }
        hideStatus();
        addBubble('assistant', '✅ Fertig – alle Schritte des Plans umgesetzt.');
        done();
    }

    async function sendMessage() {
        const text = input.value.trim();
        if (text === '' || busy) return;
        busy = true;
        input.value = '';
        send.disabled = true;
        addBubble('user', text);
        history.push({ role: 'user', text: text });

        // 1. Planungs-Phase (nichts wird ausgeführt)
        showStatus('Plan wird erstellt …');
        let plan;
        try { plan = await postJson(planUrl, { messages: history }); }
        catch (e) { plan = { ok: false, error: 'Verbindung fehlgeschlagen.' }; }
        updateBalance(plan.balance);
        if (!plan.ok) { hideStatus(); addBubble('assistant', '⚠️ ' + (plan.error || 'Der Plan konnte nicht erstellt werden.')); done(); return; }

        let steps = Array.isArray(plan.steps) ? plan.steps : [];
        if (steps.length === 0) { steps = [{ title: text, detail: '' }]; } // Fallback: als ein Schritt umsetzen
        history.push({ role: 'assistant', text: 'Plan:\n' + steps.map((s, i) => (i + 1) + '. ' + s.title).join('\n') });

        const handles = renderChecklist(steps);
        // 2. Automatisch Schritt für Schritt
        await runFrom(steps, 0, handles);
    }

    function done() {
        busy = false;
        send.disabled = false;
        status.hidden = true;
        clearInterval(statusTimer);
        input.focus();
    }

    function renderActions(target, actions) {
        (actions || []).forEach((action) => {
            const card = document.createElement('div');
            card.className = 'ai-action';
            if (action.type === 'page') {
                card.innerHTML = '<span>✓ </span><strong></strong> · <a target="_blank" rel="noopener">Im Editor öffnen</a> · <a target="_blank" rel="noopener">Ansehen ↗</a>';
                card.querySelector('strong').textContent = action.label;
                const links = card.querySelectorAll('a');
                links[0].href = action.editorUrl;
                links[1].href = action.viewUrl;
            } else if (action.type === 'image') {
                card.innerHTML = '<img alt=""> <strong></strong> · <a target="_blank" rel="noopener">Öffnen ↗</a>';
                card.querySelector('img').src = action.thumb;
                card.querySelector('strong').textContent = action.label;
                card.querySelector('a').href = action.url;
            } else if (action.type === 'link') {
                card.innerHTML = '<span>✓ </span><strong></strong>';
                card.querySelector('strong').textContent = action.label;
                if (action.editorUrl) {
                    const a = document.createElement('a');
                    a.target = '_blank'; a.rel = 'noopener'; a.textContent = 'Bearbeiten'; a.href = action.editorUrl;
                    card.append(' · ', a);
                }
                if (action.viewUrl) {
                    const a = document.createElement('a');
                    a.target = '_blank'; a.rel = 'noopener'; a.textContent = 'Ansehen ↗'; a.href = action.viewUrl;
                    card.append(' · ', a);
                }
            }
            target.appendChild(card);
        });
    }

    function addBubble(role, text, actions) {
        const wrap = document.createElement('div');
        wrap.className = 'ai-msg is-' + role;
        const bubble = document.createElement('div');
        bubble.className = 'ai-msg-bubble';
        bubble.textContent = text;
        wrap.appendChild(bubble);
        renderActions(wrap, actions);
        messagesEl.appendChild(wrap);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    // Beim Laden ans Ende des (ggf. gespeicherten) Verlaufs springen.
    messagesEl.scrollTop = messagesEl.scrollHeight;
})();
</script>
<?php endif; ?>
