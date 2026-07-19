<?php $fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c); ?>
<div class="page-actions"><a class="btn btn-ghost" href="<?= e(url('/admin/shop/customers')) ?>">← Alle Kunden</a></div>

<div class="editor-grid">
    <div>
        <div class="card">
            <h2>Bestellungen</h2>
            <?php if (empty($orders)): ?>
                <p class="muted">Dieser Kunde hat noch keine Bestellungen.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Nr.</th><th>Datum</th><th>Summe</th><th>Status</th><th class="actions-col"></th></tr></thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td data-label="Nr."><strong><?= e($o['number']) ?></strong></td>
                                <td data-label="Datum" class="muted small"><?= e(format_date_de($o['created_at'], true)) ?></td>
                                <td data-label="Summe"><?= e($fmt($o['total'])) ?></td>
                                <td data-label="Status"><span class="badge <?= e(\Models\ShopOrder::statusBadge($o['status'])) ?>"><?= e(\Models\ShopOrder::statusLabel($o['status'])) ?></span></td>
                                <td class="actions-col"><a class="btn btn-small" href="<?= e(url('/admin/shop/orders/' . $o['id'])) ?>">Ansehen</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <aside>
        <div class="card">
            <h3>Kundendaten</h3>
            <form method="post" action="<?= e(url('/admin/shop/customers/' . $customer['id'])) ?>">
                <?= csrf_field() ?>
                <div class="form-group"><label>Vorname</label><input type="text" name="first_name" value="<?= e($customer['first_name'] ?? '') ?>"></div>
                <div class="form-group"><label>Nachname</label><input type="text" name="last_name" value="<?= e($customer['last_name'] ?? '') ?>"></div>
                <div class="form-group"><label>E-Mail</label><input type="email" name="email" value="<?= e($customer['email']) ?>" required></div>
                <button type="submit" class="btn btn-primary btn-small">Speichern</button>
            </form>
            <p class="muted small">Registriert: <?= e(format_date_de($customer['created_at'], true)) ?></p>
        </div>

        <div class="card">
            <h3>Passwort zurücksetzen</h3>
            <form method="post" action="<?= e(url('/admin/shop/customers/' . $customer['id'] . '/password')) ?>">
                <?= csrf_field() ?>
                <div class="form-group"><label>Neues Passwort (mind. 6 Zeichen)</label><input type="text" name="password" minlength="6" placeholder="neues Passwort"></div>
                <button type="submit" class="btn btn-small">Passwort setzen</button>
            </form>
            <p class="muted small">Teile dem Kunden das neue Passwort mit – oder er nutzt im Shop „Passwort vergessen“.</p>
        </div>

        <div class="card">
            <form method="post" action="<?= e(url('/admin/shop/customers/' . $customer['id'] . '/delete')) ?>" data-confirm="Kunde wirklich löschen? Die Bestellungen bleiben erhalten." data-confirm-danger data-confirm-ok="Löschen">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-small btn-danger">Kunde löschen</button>
            </form>
        </div>
    </aside>
</div>
