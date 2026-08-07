<?php
$isEdit = $coupon !== null;
$action = $isEdit ? '/admin/shop/coupons/' . $coupon['id'] : '/admin/shop/coupons';
$priceStr = static fn ($cents) => $cents === null || $cents === '' ? '' : number_format(((int) $cents) / 100, 2, ',', '');
$dtLocal = static fn ($dt) => $dt ? substr((string) $dt, 0, 16) : '';
?>
<div class="card">
    <form method="post" action="<?= e(url($action)) ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group grow">
                <label for="code">Gutscheincode</label>
                <input type="text" id="code" name="code" value="<?= e($coupon['code'] ?? '') ?>" placeholder="z. B. SOMMER10" required autofocus style="text-transform:uppercase">
            </div>
            <div class="form-group checkbox-group" style="align-self:flex-end">
                <label><input type="checkbox" name="active" <?= !$isEdit || (int) ($coupon['active'] ?? 1) ? 'checked' : '' ?>> Aktiv</label>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="type">Art des Rabatts</label>
                <select id="type" name="type">
                    <option value="percent" <?= ($coupon['type'] ?? 'percent') === 'percent' ? 'selected' : '' ?>>Prozentual</option>
                    <option value="fixed" <?= ($coupon['type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fester Betrag (€)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="value">Wert</label>
                <input type="text" id="value" name="value"
                       value="<?= $isEdit ? (($coupon['type'] ?? 'percent') === 'fixed' ? e($priceStr($coupon['value'])) : (int) $coupon['value']) : '' ?>"
                       placeholder="z. B. 10" inputmode="decimal">
                <p class="muted small">Prozent (z. B. 10) oder Euro-Betrag (z. B. 5,00), je nach gewählter Art.</p>
            </div>
            <div class="form-group">
                <label for="min_subtotal">Mindestbestellwert (€, optional)</label>
                <input type="text" id="min_subtotal" name="min_subtotal" value="<?= e($priceStr($coupon['min_subtotal'] ?? '')) ?>" placeholder="z. B. 50,00" inputmode="decimal">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="starts_at">Gültig ab (optional)</label>
                <input type="datetime-local" id="starts_at" name="starts_at" value="<?= e($dtLocal($coupon['starts_at'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="ends_at">Gültig bis (optional)</label>
                <input type="datetime-local" id="ends_at" name="ends_at" value="<?= e($dtLocal($coupon['ends_at'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="usage_limit">Nutzungslimit (optional)</label>
                <input type="number" id="usage_limit" name="usage_limit" min="1" value="<?= e($coupon['usage_limit'] ?? '') ?>" placeholder="unbegrenzt">
            </div>
        </div>
        <?php if ($isEdit): ?>
            <p class="muted small">Bisher <?= (int) $coupon['used_count'] ?>× eingelöst.</p>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Speichern' : 'Anlegen' ?></button>
            <a class="btn btn-ghost" href="<?= e(url('/admin/shop/coupons')) ?>">Abbrechen</a>
        </div>
    </form>
</div>
