<?php
$fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c);
$defaultDate = date('Y-m-d', strtotime('+3 weekdays'));
?>
<div class="card">
    <p class="muted small">
        Zahlungen per SEPA-Lastschrift laufen ohne Zahlungsdienstleister: Du sammelst die offenen Lastschriften hier,
        lädst sie als SEPA-Sammeldatei (pain.008) herunter und reichst sie in deinem Online-Banking oder direkt bei
        deiner Bank ein. Nach dem Download gelten die Bestellungen als „eingereicht" – markiere sie erst dann manuell
        als „Bezahlt" (Bestelldetail), wenn der Betrag auf deinem Konto eingegangen und die 8-wöchige Widerspruchsfrist
        unkritisch ist.
    </p>

    <?php if (empty($orders)): ?>
        <p class="muted">Keine offenen SEPA-Lastschriften.</p>
    <?php else: ?>
        <form method="post" action="<?= e(url('/admin/shop/sepa/export')) ?>">
            <?= csrf_field() ?>
            <div class="form-group" style="max-width:260px">
                <label for="collection_date">Fälligkeitsdatum (Einzug)</label>
                <input type="date" id="collection_date" name="collection_date" value="<?= e($defaultDate) ?>" required>
                <p class="muted small">Mindestens 1–2 Bankarbeitstage in der Zukunft, je nach Vorlaufzeit deiner Bank.</p>
            </div>
            <table class="table">
                <thead><tr><th><input type="checkbox" id="check-all"></th><th>Bestellung</th><th>Kontoinhaber</th><th>IBAN</th><th>Betrag</th><th>Mandat</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><input type="checkbox" name="order_ids[]" value="<?= (int) $o['id'] ?>" class="order-check"></td>
                            <td data-label="Bestellung"><a href="<?= e(url('/admin/shop/orders/' . $o['id'])) ?>"><strong><?= e($o['number']) ?></strong></a></td>
                            <td data-label="Kontoinhaber"><?= e($o['sepa_account_holder'] ?? '') ?></td>
                            <td data-label="IBAN"><code><?= e(\Core\Iban::mask((string) ($o['sepa_iban'] ?? ''))) ?></code></td>
                            <td data-label="Betrag"><?= e($fmt($o['total'])) ?></td>
                            <td class="muted small" data-label="Mandat"><?= e($o['sepa_mandate_ref'] ?? '') ?><br><?= e(format_date_de($o['sepa_mandate_date'] ?? null)) ?></td>
                            <td data-label="Status">
                                <?= !empty($o['sepa_submitted_at'])
                                    ? '<span class="badge badge-green">Eingereicht ' . e(format_date_de($o['sepa_submitted_at'])) . '</span>'
                                    : '<span class="badge badge-amber">Offen</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">SEPA-Sammeldatei herunterladen</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<script>
(function () {
    var all = document.getElementById('check-all');
    if (!all) return;
    all.addEventListener('change', function () {
        document.querySelectorAll('.order-check').forEach(function (c) { c.checked = all.checked; });
    });
})();
</script>
