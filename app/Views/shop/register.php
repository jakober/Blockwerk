<?php $f = $form ?? []; $val = static fn ($k) => e($f[$k] ?? ''); ?>
<div class="shop">
    <?= \Core\View::fetch('shop/_bar', []) ?>
    <h1 class="cms-heading">Konto erstellen</h1>
    <div class="shop-account-form">
        <form method="post" action="<?= e(\Core\Shop::url('registrieren')) ?>" class="shop-checkout-form">
            <?= csrf_field() ?>
            <div class="shop-form-row">
                <label>Vorname<input type="text" name="first_name" value="<?= $val('first_name') ?>"></label>
                <label>Nachname<input type="text" name="last_name" value="<?= $val('last_name') ?>"></label>
            </div>
            <label>E-Mail*<input type="email" name="email" value="<?= $val('email') ?>" required></label>
            <label>Passwort* (mindestens 6 Zeichen)<input type="password" name="password" required minlength="6"></label>
            <div class="shop-checkout-submit"><button type="submit" class="cms-button">Konto erstellen</button></div>
        </form>
        <p class="muted small">Schon ein Konto? <a href="<?= e(\Core\Shop::url('login')) ?>">Anmelden</a></p>
    </div>
</div>
