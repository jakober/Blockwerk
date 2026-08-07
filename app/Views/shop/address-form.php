<?php
$isEdit = $address !== null;
$action = $isEdit ? \Core\Shop::url('konto/adressen/' . $address['id']) : \Core\Shop::url('konto/adressen');
$val = static fn ($k) => e($address[$k] ?? '');
?>
<div class="shop">
    <?= \Core\View::fetch('shop/_bar', []) ?>
    <h1 class="cms-heading"><?= $isEdit ? 'Adresse bearbeiten' : 'Neue Adresse' ?></h1>

    <form method="post" action="<?= e($action) ?>" class="shop-checkout-form">
        <?= csrf_field() ?>
        <label>Bezeichnung (optional, z. B. „Zuhause", „Büro")<input type="text" name="label" value="<?= $val('label') ?>"></label>
        <div class="shop-form-row">
            <label>Vorname<input type="text" name="first_name" value="<?= $val('first_name') ?>"></label>
            <label>Nachname<input type="text" name="last_name" value="<?= $val('last_name') ?>"></label>
        </div>
        <label>Firma (optional)<input type="text" name="company" value="<?= $val('company') ?>"></label>
        <label>Straße &amp; Hausnummer<input type="text" name="street" value="<?= $val('street') ?>"></label>
        <div class="shop-form-row">
            <label>PLZ<input type="text" name="zip" value="<?= $val('zip') ?>"></label>
            <label>Ort<input type="text" name="city" value="<?= $val('city') ?>"></label>
        </div>
        <label>Land<input type="text" name="country" value="<?= $address['country'] ?? '' ? $val('country') : 'Deutschland' ?>"></label>
        <label>Telefon (optional)<input type="text" name="phone" value="<?= $val('phone') ?>"></label>

        <div class="shop-checkout-submit">
            <button type="submit" class="cms-button">Speichern</button>
            <a class="cms-button cms-button-ghost" href="<?= e(\Core\Shop::url('konto/adressen')) ?>">Abbrechen</a>
        </div>
    </form>
</div>
