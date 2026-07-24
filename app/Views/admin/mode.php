<div style="max-width:640px">
    <h1 style="margin:0 0 4px">Website-Modus</h1>
    <p class="muted">Aktuell aktiv: <strong>Content-Management-System</strong> (mit Datenbank, Block-Editor, Shop …).</p>

    <div class="card">
        <h3 style="margin-top:0">In den KI-Webseiten-Modus wechseln</h3>
        <p class="muted small">Im KI-Modus wird deine öffentliche Website durch eine von der KI erzeugte HTML/CSS/jQuery-Seite ersetzt. <strong>Dein CMS (Datenbank, Seiten, Shop) bleibt vollständig erhalten</strong> und ist jederzeit wieder aktivierbar – beide Systeme bestehen unabhängig nebeneinander. Du brauchst einen Lizenzschlüssel (beginnt mit <code>bw-</code>) und legst ein Passwort für das KI-Backend fest.</p>

        <form method="post" action="<?= e(url('/admin/mode/ai')) ?>" data-confirm="In den KI-Webseiten-Modus wechseln? Deine öffentliche Seite wird dann von der KI-Seite bedient (CMS bleibt gespeichert)." data-confirm-ok="Wechseln">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="license">Lizenzschlüssel</label>
                <input type="text" id="license" name="license" placeholder="bw-…" required>
            </div>
            <div class="form-group">
                <label for="site_name">Name der Website (optional)</label>
                <input type="text" id="site_name" name="site_name" value="<?= e(\Models\Setting::get('site_name', '')) ?>">
            </div>
            <div class="form-group">
                <label for="username">Backend-Benutzername (KI-Modus)</label>
                <input type="text" id="username" name="username" value="admin" required>
            </div>
            <div class="form-row">
                <div class="form-group grow"><label for="password">Passwort (mind. 8 Zeichen)</label><input type="password" id="password" name="password" required></div>
                <div class="form-group grow"><label for="password_repeat">Passwort wiederholen</label><input type="password" id="password_repeat" name="password_repeat" required></div>
            </div>
            <details style="margin-bottom:12px">
                <summary class="muted small" style="cursor:pointer">Erweitert: eigene KI-Dienst-URL</summary>
                <div class="form-group" style="margin-top:8px"><label for="service_url">Dienst-URL (leer = Standard)</label><input type="text" id="service_url" name="service_url" placeholder="https://…/ai-server"></div>
            </details>
            <button type="submit" class="btn btn-primary">In den KI-Modus wechseln</button>
        </form>
    </div>
</div>
