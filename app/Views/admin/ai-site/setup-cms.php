<?php /** @var bool $hasDb */ ?>
<div style="max-width:640px">
    <h1 style="margin:0 0 4px">Zum CMS-Modus wechseln</h1>
    <p class="muted">Die aktuelle KI-Webseite bleibt vollständig gespeichert und lässt sich später wieder aktivieren – es wird nichts gelöscht.</p>

    <?php if ($hasDb): ?>
        <div class="card">
            <p>Für diese Installation ist bereits eine Datenbank hinterlegt. Du kannst direkt in den CMS-Modus wechseln.</p>
            <form method="post" action="<?= e(url('/admin/ai-site/switch-to-cms')) ?>" data-confirm="Jetzt in den CMS-Modus wechseln? Die KI-Webseite bleibt gespeichert." data-confirm-ok="Wechseln">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">In den CMS-Modus wechseln</button>
            </form>
        </div>
    <?php else: ?>
        <div class="card">
            <p>Der CMS-Modus benötigt eine <strong>Datenbank</strong>. Gib die Zugangsdaten ein – Tabellen und eine Startseite werden automatisch angelegt.</p>
            <form method="post" action="<?= e(url('/admin/ai-site/switch-to-cms')) ?>">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group grow"><label for="host">Datenbank-Host</label><input type="text" id="host" name="host" value="localhost" required></div>
                    <div class="form-group"><label for="port">Port</label><input type="number" id="port" name="port" value="3306" required></div>
                </div>
                <div class="form-group"><label for="name">Datenbankname</label><input type="text" id="name" name="name" required></div>
                <div class="form-group"><label for="user">DB-Benutzer</label><input type="text" id="user" name="user" required></div>
                <div class="form-group"><label for="pass">DB-Passwort</label><input type="password" id="pass" name="pass"></div>
                <hr>
                <div class="form-group"><label for="site_name">Name der Website</label><input type="text" id="site_name" name="site_name" required></div>
                <div class="form-group"><label for="username">Admin-Benutzername</label><input type="text" id="username" name="username" value="admin" required></div>
                <div class="form-group"><label for="password">Admin-Passwort (mind. 8 Zeichen)</label><input type="password" id="password" name="password" required></div>
                <button type="submit" class="btn btn-primary">Datenbank einrichten &amp; in den CMS-Modus wechseln</button>
            </form>
        </div>
    <?php endif; ?>

    <p style="margin-top:14px"><a class="muted small" href="<?= e(url('/admin')) ?>">← Zurück</a></p>
</div>

<script src="<?= e(asset('/assets/js/admin-dialog.js')) ?>" defer></script>
