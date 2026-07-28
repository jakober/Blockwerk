<div class="card narrow">
    <form method="post" action="<?= e(url('/admin/settings')) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="site_name">Name der Website</label>
            <input type="text" id="site_name" name="site_name" value="<?= e($siteName) ?>" required>
        </div>
        <div class="form-group">
            <label for="site_logo">Logo der Website</label>
            <div class="image-field">
                <input type="text" id="site_logo" name="site_logo" value="<?= e($siteLogo ?? '') ?>" placeholder="Bild-URL oder aus der Mediathek wählen – leer = nur der Name">
                <button type="button" class="btn" data-media-pick="#site_logo">Mediathek</button>
            </div>
            <p class="muted small">Erscheint im Kopf jeder Seite (Platzhalter <code>{{logo}}</code> im Layout) und auf Rechnungen. Funktioniert in klassischen Layouts genauso wie im visuellen Baukasten.</p>
        </div>
        <div class="form-group">
            <label for="home_page">Startseite</label>
            <select id="home_page" name="home_page">
                <option value="0">– Erste veröffentlichte Seite –</option>
                <?php foreach ($pages as $page): ?>
                    <option value="<?= (int) $page['id'] ?>" <?= $homePage === (int) $page['id'] ? 'selected' : '' ?>>
                        <?= str_repeat('&nbsp;&nbsp;&nbsp;', (int) $page['depth']) ?><?= e($page['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="contact_email">E-Mail-Empfänger für Kontaktformulare</label>
            <input type="email" id="contact_email" name="contact_email" value="<?= e($contactEmail) ?>" placeholder="z. B. info@deine-domain.de">
            <p class="muted small">Kontaktformular-Blöcke ohne eigenen Empfänger senden an diese Adresse.</p>
        </div>

        <div class="form-group">
            <label for="languages">Sprachen (Komma-getrennt, erste = Standardsprache)</label>
            <input type="text" id="languages" name="languages" value="<?= e(implode(',', cms_langs())) ?>" placeholder="de,en">
            <p class="muted small">Bei mehreren Sprachen bekommt jede Seite eine Sprach-Zuordnung; andere Sprachen sind unter /en/… usw. erreichbar. Sprachumschalter im Layout: <code>{{languages}}</code></p>
        </div>

        <div class="form-group checkbox-group">
            <label><input type="checkbox" name="cache_enabled" <?= \Models\Setting::get('cache_enabled', '0') === '1' ? 'checked' : '' ?>> Seiten-Cache aktivieren (schnellere Website – fertige Seiten werden zwischengespeichert; jede Änderung im Admin leert den Cache automatisch)</label>
        </div>

        <h2 style="margin-top:28px" id="shop">🛒 Shop</h2>
        <p class="muted small">Aktiviere den Online-Shop, wenn du Produkte verkaufen möchtest. Erst dann erscheinen der Shop-Bereich im Menü (Produkte, Kategorien, Bestellungen, Shop-Einstellungen) und die Shop-Seiten auf der Website. Die Detail-Einstellungen (Hauptseite, Zahlungs- &amp; Versandarten) findest du danach unter <strong>Shop → Shop-Einstellungen</strong>.</p>
        <div class="form-group checkbox-group">
            <label><input type="checkbox" name="shop_enabled" <?= ($shopEnabled ?? '0') === '1' ? 'checked' : '' ?>> Shop-Funktion aktivieren</label>
        </div>

        <h2 style="margin-top:28px" id="ki">✨ KI-Assistent</h2>
        <p class="muted small">Der KI-Assistent erstellt Seiten, Texte und Bilder direkt im CMS. Dafür brauchst du einen Lizenzschlüssel mit Token-Guthaben vom Blockwerk-Orange-Anbieter.</p>

        <?php $aiBuyUrl = \Core\Ai::buyUrl(); ?>
        <div class="ai-key-help" style="background:color-mix(in srgb,var(--primary) 6%,#fff);border:1px solid color-mix(in srgb,var(--primary) 22%,var(--border));border-radius:12px;padding:16px 18px;margin:14px 0 18px">
            <strong style="display:block;margin-bottom:8px">🔑 Schlüssel besorgen &amp; eintragen – in 3 Schritten</strong>
            <ol style="margin:0;padding-left:20px;line-height:1.75;font-size:14px">
                <li><strong>Guthaben kaufen.</strong> Öffne die <a href="<?= e($aiBuyUrl) ?>" target="_blank" rel="noopener">Kaufseite für KI-Guthaben ↗</a>, wähle ein Paket und zahle per PayPal. Deinen persönlichen Schlüssel (beginnt mit <code>bw-</code>) bekommst du <strong>sofort per E-Mail</strong> – zusammen mit der Rechnung.</li>
                <li><strong>Schlüssel einfügen.</strong> Kopiere den <code>bw-</code>-Schlüssel aus der E-Mail und füge ihn unten in das Feld <em>„Lizenzschlüssel“</em> ein. Die <em>Dienst-URL</em> lässt du leer (Standard-Dienst).</li>
                <li><strong>Speichern &amp; loslegen.</strong> Auf <em>Speichern</em> klicken – fertig. Der <a href="<?= e(url('/admin/ai')) ?>">KI-Assistent</a> zeigt dann dein Token-Guthaben an und ist einsatzbereit.</li>
            </ol>
            <p class="muted small" style="margin:10px 0 0">Guthaben fast aufgebraucht? Denselben Schlüssel kannst du auf der Kaufseite jederzeit <strong>aufladen</strong> – er bleibt gleich, das Guthaben wird erhöht.</p>
        </div>

        <div class="form-group">
            <label for="ai_service_url">Dienst-URL (leer = Standard-Dienst)</label>
            <input type="url" id="ai_service_url" name="ai_service_url" value="<?= e(\Models\Setting::get('ai_service_url', '')) ?>" placeholder="<?= e(\Core\Ai::DEFAULT_SERVICE_URL) ?>">
        </div>
        <div class="form-group">
            <label for="ai_license_key">Lizenzschlüssel</label>
            <input type="text" id="ai_license_key" name="ai_license_key" value="<?= e(\Models\Setting::get('ai_license_key', '')) ?>" placeholder="bw-…">
            <p class="muted small">Aus der Bestätigungs-E-Mail einfügen. Nach dem Speichern zeigt der <a href="<?= e(url('/admin/ai')) ?>">KI-Assistent</a> dein Token-Guthaben an.</p>
        </div>

        <h2 style="margin-top:28px">E-Mail-Versand</h2>
        <div class="form-group checkbox-group">
            <label><input type="radio" name="mail_transport" value="mail" <?= $mail['transport'] !== 'smtp' ? 'checked' : '' ?> data-mail-toggle> Über den Mailserver des Hosters (PHP <code>mail()</code>) – Standard</label>
            <label><input type="radio" name="mail_transport" value="smtp" <?= $mail['transport'] === 'smtp' ? 'checked' : '' ?> data-mail-toggle> Eigener SMTP-Server</label>
        </div>

        <div id="smtp-settings" <?= $mail['transport'] === 'smtp' ? '' : 'hidden' ?>>
            <div class="form-row">
                <div class="form-group grow">
                    <label for="smtp_host">SMTP-Host</label>
                    <input type="text" id="smtp_host" name="smtp_host" value="<?= e($mail['smtp_host']) ?>" placeholder="z. B. smtp.strato.de">
                </div>
                <div class="form-group">
                    <label for="smtp_port">Port</label>
                    <input type="number" id="smtp_port" name="smtp_port" value="<?= e($mail['smtp_port']) ?>">
                </div>
                <div class="form-group">
                    <label for="smtp_encryption">Verschlüsselung</label>
                    <select id="smtp_encryption" name="smtp_encryption">
                        <option value="tls" <?= $mail['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>STARTTLS (Port 587)</option>
                        <option value="ssl" <?= $mail['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL/TLS (Port 465)</option>
                        <option value="none" <?= $mail['smtp_encryption'] === 'none' ? 'selected' : '' ?>>Keine</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group grow">
                    <label for="smtp_user">Benutzername</label>
                    <input type="text" id="smtp_user" name="smtp_user" value="<?= e($mail['smtp_user']) ?>" autocomplete="off">
                </div>
                <div class="form-group grow">
                    <label for="smtp_pass">Passwort <?= $mail['smtp_pass'] !== '' ? '(gespeichert – leer lassen zum Behalten)' : '' ?></label>
                    <input type="password" id="smtp_pass" name="smtp_pass" value="" autocomplete="new-password" placeholder="<?= $mail['smtp_pass'] !== '' ? '••••••••' : '' ?>">
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group grow">
                <label for="mail_from">Absender-Adresse (leer = automatisch noreply@deine-domain)</label>
                <input type="email" id="mail_from" name="mail_from" value="<?= e($mail['from']) ?>" placeholder="z. B. kontakt@deine-domain.de">
            </div>
            <div class="form-group grow">
                <label for="mail_from_name">Absender-Name (leer = Name der Website)</label>
                <input type="text" id="mail_from_name" name="mail_from_name" value="<?= e($mail['from_name']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="test_email">Testmail senden an</label>
            <div class="image-field">
                <input type="email" id="test_email" name="test_email" placeholder="<?= e($contactEmail ?: 'deine@email.de') ?>">
                <button type="submit" name="action" value="test" class="btn">Speichern &amp; Testmail senden</button>
            </div>
            <p class="muted small">Speichert alle Einstellungen und schickt sofort eine Testmail über den gewählten Versandweg – so siehst du direkt, ob alles funktioniert.</p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Speichern</button>
        </div>
    </form>
</div>

<script src="<?= e(asset('/assets/js/admin-tools.js')) ?>"></script>
<script>
document.querySelectorAll('[data-mail-toggle]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.getElementById('smtp-settings').hidden =
            document.querySelector('[data-mail-toggle][value="smtp"]').checked === false;
    });
});
</script>
