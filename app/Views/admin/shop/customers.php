<div class="card">
    <?php if (empty($customers)): ?>
        <p class="muted">Noch keine Kundenkonten. Kunden können sich im Shop registrieren oder bei der Bestellung ein Konto anlegen.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Kunde</th><th>E-Mail</th><th>Registriert</th><th>Bestellungen</th><th class="actions-col">Aktionen</th></tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $c): ?>
                    <?php $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')); ?>
                    <tr>
                        <td data-label="Kunde"><a href="<?= e(url('/admin/shop/customers/' . $c['id'])) ?>"><strong><?= e($name !== '' ? $name : '—') ?></strong></a></td>
                        <td data-label="E-Mail"><?= e($c['email']) ?></td>
                        <td data-label="Registriert" class="muted small"><?= e(format_date_de($c['created_at'])) ?></td>
                        <td data-label="Bestellungen"><?= (int) ($c['order_count'] ?? 0) ?></td>
                        <td class="actions-col">
                            <a class="btn btn-small" href="<?= e(url('/admin/shop/customers/' . $c['id'])) ?>">Details</a>
                            <form method="post" action="<?= e(url('/admin/shop/customers/' . $c['id'] . '/delete')) ?>" class="inline" data-confirm="Kunde „<?= e($c['email']) ?>“ wirklich löschen? Die Bestellungen bleiben erhalten." data-confirm-danger data-confirm-ok="Löschen">
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
