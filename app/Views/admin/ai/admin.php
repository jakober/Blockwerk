<?php
$mask = static fn (string $value): string => $value === '' ? '' : '••••' . substr($value, -4);
?>

<?php if (!$available): ?>
    <div class="card narrow">
        <h2>KI-Dienst noch nicht installiert</h2>
        <p class="muted">Das Verzeichnis <code>ai-server/</code> fehlt noch. Führe auf dieser Domain einmal ein
        <a href="<?= e(url('/admin/update')) ?>">Update</a> aus – auf der Anbieter-Domain wird der Dienst automatisch mitinstalliert.</p>
    </div>
<?php else: ?>

<div class="card narrow">
    <h2>🗝 API-Schlüssel &amp; Preise</h2>
    <p class="muted small">Diese Werte werden direkt in die Dienst-Konfiguration (<code>ai-server/config.php</code>) geschrieben – kein FTP nötig.
    Aus Sicherheitsgründen werden gespeicherte Schlüssel <strong>nie wieder im Klartext angezeigt</strong> –
    das Feld ist danach leer, der Schlüssel aber sicher hinterlegt (✓). Ein leeres Feld lässt den gespeicherten Wert unverändert.</p>
    <form method="post" action="<?= e(url('/admin/ai-admin/config')) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="anthropic_key">Anthropic-API-Key (Chat / Claude)
                <?= !empty($config['anthropic_key']) ? '<span class="badge badge-green">✓ hinterlegt</span>' : '' ?></label>
            <input type="password" id="anthropic_key" name="anthropic_key" autocomplete="off"
                   placeholder="<?= !empty($config['anthropic_key']) ? 'gespeichert: ' . e($mask($config['anthropic_key'])) . ' – zum Ändern neuen Schlüssel eingeben' : 'sk-ant-…' ?>">
        </div>
        <div class="form-group">
            <label for="openai_key">OpenAI-API-Key (Bildgenerierung)
                <?= !empty($config['openai_key']) ? '<span class="badge badge-green">✓ hinterlegt</span>' : '' ?></label>
            <input type="password" id="openai_key" name="openai_key" autocomplete="off"
                   placeholder="<?= !empty($config['openai_key']) ? 'gespeichert: ' . e($mask($config['openai_key'])) . ' – zum Ändern neuen Schlüssel eingeben' : 'sk-…' ?>">
        </div>
        <div class="form-row">
            <div class="form-group grow">
                <label for="model">Claude-Modell</label>
                <input type="text" id="model" name="model" value="<?= e($config['model'] ?? 'claude-sonnet-5') ?>">
            </div>
            <div class="form-group grow">
                <label for="image_model">Bild-Modell</label>
                <input type="text" id="image_model" name="image_model" value="<?= e($config['image_model'] ?? 'gpt-image-1') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group grow">
                <label for="image_token_price">Token-Preis pro Bild</label>
                <input type="number" id="image_token_price" name="image_token_price" value="<?= (int) ($config['image_token_price'] ?? 25000) ?>">
            </div>
            <div class="form-group grow">
                <label for="rate_limit_per_minute">Max. Anfragen pro Lizenz/Minute</label>
                <input type="number" id="rate_limit_per_minute" name="rate_limit_per_minute" value="<?= (int) ($config['rate_limit_per_minute'] ?? 20) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="admin_password">Passwort für <code>ai-server/admin.php</code> (optional – die Verwaltung hier braucht es nicht)
                <?= !empty($config['admin_password']) ? '<span class="badge badge-green">✓ hinterlegt</span>' : '' ?></label>
            <input type="password" id="admin_password" name="admin_password" autocomplete="new-password"
                   placeholder="<?= !empty($config['admin_password']) ? 'gespeichert: ' . e($mask($config['admin_password'])) . ' – zum Ändern neues Passwort eingeben' : '' ?>">
        </div>
        <div class="form-group checkbox-group">
            <label><input type="checkbox" name="mock" <?= !empty($config['mock']) ? 'checked' : '' ?>> Mock-Modus (nur zum Testen – keine echten KI-Antworten!)</label>
        </div>
        <button type="submit" class="btn btn-primary"><?= $configured ? 'Konfiguration speichern' : 'Dienst jetzt aktivieren' ?></button>
    </form>
</div>

<div class="card">
    <h2>Kunden-Lizenzen</h2>
    <?php if (!$sqliteOk): ?>
        <p class="muted">Die PHP-Erweiterung <code>pdo_sqlite</code> fehlt auf diesem Server.</p>
    <?php else: ?>
        <form method="post" action="<?= e(url('/admin/ai-admin/license')) ?>" class="ai-license-create">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <input type="text" name="name" placeholder="Kunde / Installation">
            <input type="number" name="tokens" value="500000" title="Start-Guthaben (Tokens)">
            <button type="submit" class="btn btn-primary">+ Lizenz anlegen</button>
        </form>

        <?php if (empty($licenses)): ?>
            <p class="muted">Noch keine Lizenzen angelegt.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Kunde</th><th>Schlüssel</th><th>System (Domain)</th><th>Guthaben</th><th>Verbraucht</th><th>Status</th><th class="actions-col">Aktionen</th></tr></thead>
                <tbody>
                <?php foreach ($licenses as $lic): ?>
                    <tr>
                        <td><strong><?= e($lic['name']) ?></strong><?= $lic['license_key'] === $ownKey ? ' <span class="badge badge-green">diese Installation</span>' : '' ?></td>
                        <td><code><?= e($lic['license_key']) ?></code></td>
                        <td>
                            <?php if (!empty($lic['last_domain'])): ?>
                                <a href="https://<?= e($lic['last_domain']) ?>" target="_blank" rel="noopener"><?= e($lic['last_domain']) ?></a>
                                <?php if (!empty($lic['last_seen'])): ?>
                                    <br><span class="muted small">zuletzt aktiv: <?= e(date('d.m.Y H:i', (int) strtotime((string) $lic['last_seen']))) ?> UTC</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="muted small">noch nicht gesehen</span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format(max(0, $lic['tokens_total'] - $lic['tokens_used']), 0, ',', '.') ?></td>
                        <td class="muted"><?= number_format((int) $lic['tokens_used'], 0, ',', '.') ?></td>
                        <td><?= $lic['active'] ? '<span class="badge badge-green">aktiv</span>' : '<span class="badge">gesperrt</span>' ?></td>
                        <td class="actions-col">
                            <form method="post" action="<?= e(url('/admin/ai-admin/license')) ?>" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="topup">
                                <input type="hidden" name="key" value="<?= e($lic['license_key']) ?>">
                                <input type="number" name="tokens" value="500000" style="width:110px;display:inline-block">
                                <button type="submit" class="btn btn-small">+ Aufladen</button>
                            </form>
                            <?php if ($lic['license_key'] !== $ownKey): ?>
                                <form method="post" action="<?= e(url('/admin/ai-admin/license')) ?>" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="use">
                                    <input type="hidden" name="key" value="<?= e($lic['license_key']) ?>">
                                    <button type="submit" class="btn btn-small" title="Diese Lizenz für den KI-Assistenten dieser Installation verwenden">Hier nutzen</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/admin/ai-admin/license')) ?>" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="key" value="<?= e($lic['license_key']) ?>">
                                <button type="submit" class="btn btn-small <?= $lic['active'] ? 'btn-danger' : '' ?>"><?= $lic['active'] ? 'Sperren' : 'Aktivieren' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p class="muted small">Chat-Anfragen kosten die tatsächlichen Tokens, jedes generierte Bild <?= number_format((int) ($config['image_token_price'] ?? 25000), 0, ',', '.') ?> Tokens. Zum Token-Verkauf einfach die Lizenz des Kunden aufladen.</p>
    <?php endif; ?>
</div>

<?php endif; ?>
