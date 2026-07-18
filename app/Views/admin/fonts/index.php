<div class="card narrow">
    <h2>Google-Schrift hinzufügen</h2>
    <p class="muted small">Die Schrift wird einmalig von Google Fonts heruntergeladen und dauerhaft <strong>lokal auf deinem Server</strong> gespeichert – Besucher deiner Website stellen keine Verbindung zu Google her (DSGVO-freundlich). Danach kannst du sie im Layout unter „Design“ auswählen.</p>
    <form method="post" action="<?= e(url('/admin/fonts')) ?>" class="upload-form">
        <?= csrf_field() ?>
        <input type="text" name="family" placeholder='z. B. "Inter", "Roboto" oder "Playfair Display"' required>
        <button type="submit" class="btn btn-primary">Herunterladen</button>
    </form>
    <p class="muted small">Alle verfügbaren Schriften findest du auf <a href="https://fonts.google.com" target="_blank" rel="noopener">fonts.google.com</a> – hier einfach den Namen eintragen.</p>
</div>

<div class="card">
    <?php if (empty($fonts)): ?>
        <p class="muted">Noch keine Schriften installiert. Ohne eigene Schrift wird die Systemschrift verwendet.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Schrift</th><th>Vorschau</th><th class="actions-col">Aktionen</th></tr>
            </thead>
            <tbody>
                <?php foreach ($fonts as $font): ?>
                    <tr>
                        <td><strong><?= e($font['name']) ?></strong></td>
                        <td>
                            <link rel="stylesheet" href="<?= e(url('/uploads/fonts/' . $font['folder'] . '/font.css')) ?>">
                            <span style="font-family:'<?= e($font['family']) ?>',sans-serif;font-size:19px">Zwölf Boxkämpfer jagen Viktor quer über den Sylter Deich 0123</span>
                        </td>
                        <td class="actions-col">
                            <form method="post" action="<?= e(url('/admin/fonts/' . $font['id'] . '/delete')) ?>" class="inline" data-confirm="Schrift „<?= e($font['name']) ?>“ wirklich löschen?" data-confirm-danger data-confirm-ok="Löschen">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger">Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
