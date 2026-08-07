<div class="shop">
    <?= \Core\View::fetch('shop/_bar', []) ?>
    <div class="shop-account-head">
        <h1 class="cms-heading">Adressbuch</h1>
        <a class="cms-button" href="<?= e(\Core\Shop::url('konto/adressen/neu')) ?>">+ Neue Adresse</a>
    </div>
    <p><a href="<?= e(\Core\Shop::url('konto')) ?>">← Zurück zu meinem Konto</a></p>

    <?php if (empty($addresses)): ?>
        <p class="muted">Noch keine Adressen gespeichert.</p>
    <?php else: ?>
        <div class="shop-address-list">
            <?php foreach ($addresses as $a): ?>
                <div class="shop-address-card">
                    <?php if ((int) $a['is_default_shipping']): ?><span class="badge badge-green">Standard</span><?php endif; ?>
                    <p>
                        <strong><?= e($a['label'] !== '' && $a['label'] !== null ? $a['label'] : trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))) ?></strong><br>
                        <?= e(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))) ?><br>
                        <?php if (!empty($a['company'])): ?><?= e($a['company']) ?><br><?php endif; ?>
                        <?= e($a['street'] ?? '') ?><br>
                        <?= e(trim(($a['zip'] ?? '') . ' ' . ($a['city'] ?? ''))) ?><br>
                        <?= e($a['country'] ?? '') ?>
                    </p>
                    <div class="shop-address-actions">
                        <a class="btn btn-small" href="<?= e(\Core\Shop::url('konto/adressen/' . $a['id'] . '/bearbeiten')) ?>">Bearbeiten</a>
                        <?php if (!(int) $a['is_default_shipping']): ?>
                            <form method="post" action="<?= e(\Core\Shop::url('konto/adressen/' . $a['id'] . '/standard')) ?>" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small">Als Standard</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= e(\Core\Shop::url('konto/adressen/' . $a['id'] . '/loeschen')) ?>" class="inline" data-confirm="Adresse löschen?" data-confirm-danger data-confirm-ok="Löschen">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-small btn-danger">Löschen</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
