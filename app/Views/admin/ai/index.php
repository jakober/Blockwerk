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
        <div class="ai-balance" id="ai-balance" title="Verbleibendes Token-Guthaben">
            <?php if ($balance !== null): ?>
                Guthaben: <strong><?= number_format((int) $balance, 0, ',', '.') ?></strong> Tokens
            <?php elseif ($balanceError !== null): ?>
                <span class="ai-balance-error"><?= e($balanceError) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="card ai-chat" id="ai-chat">
        <div class="ai-messages" id="ai-messages">
            <div class="ai-msg is-assistant">
                <div class="ai-msg-bubble">Hallo! Ich kenne dein Blockwerk Orange in- und auswendig. Sag mir zum Beispiel:<br>
                „<em>Erstelle eine Startseite für ein Café mit Hero-Bild, drei Vorteilen und Kontaktformular</em>“ –
                ich lege die Seite komplett an, inklusive Texten und generierten Bildern.</div>
            </div>
        </div>
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
    const history = [];
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

    function sendMessage() {
        const text = input.value.trim();
        if (text === '' || busy) return;
        busy = true;
        input.value = '';
        send.disabled = true;
        addBubble('user', text);
        history.push({ role: 'user', text: text });

        let step = 0;
        status.hidden = false;
        statusText.textContent = statusLines[0];
        statusTimer = setInterval(() => {
            step = (step + 1) % statusLines.length;
            statusText.textContent = statusLines[step];
        }, 6000);

        fetch(chatUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ messages: history }),
        }).then((r) => r.json()).then((res) => {
            done();
            if (res.ok) {
                addBubble('assistant', res.text, res.actions || []);
                history.push({ role: 'assistant', text: res.text });
            } else {
                addBubble('assistant', '⚠️ ' + (res.error || 'Unbekannter Fehler.'), res.actions || []);
            }
            if (res.balance !== null && res.balance !== undefined) {
                balanceEl.innerHTML = 'Guthaben: <strong>' + new Intl.NumberFormat('de-DE').format(res.balance) + '</strong> Tokens';
            }
        }).catch(() => {
            done();
            addBubble('assistant', '⚠️ Verbindung fehlgeschlagen – bitte erneut versuchen.');
        });
    }

    function done() {
        busy = false;
        send.disabled = false;
        status.hidden = true;
        clearInterval(statusTimer);
        input.focus();
    }

    function addBubble(role, text, actions) {
        const wrap = document.createElement('div');
        wrap.className = 'ai-msg is-' + role;
        const bubble = document.createElement('div');
        bubble.className = 'ai-msg-bubble';
        bubble.textContent = text;
        wrap.appendChild(bubble);
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
            }
            wrap.appendChild(card);
        });
        messagesEl.appendChild(wrap);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }
})();
</script>
<?php endif; ?>
