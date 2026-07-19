<div class="shop">
    <?= \Core\View::fetch('shop/_bar', []) ?>
    <h1 class="cms-heading">Anmelden</h1>
    <div class="shop-account-form">
        <form method="post" action="<?= e(\Core\Shop::url('login')) ?>" class="shop-checkout-form">
            <?= csrf_field() ?>
            <label>E-Mail<input type="email" name="email" value="<?= e($email ?? '') ?>" required autofocus></label>
            <label>Passwort<input type="password" name="password" required></label>
            <div class="shop-checkout-submit"><button type="submit" class="cms-button">Anmelden</button></div>
        </form>
        <p class="muted small"><a href="<?= e(\Core\Shop::url('passwort-vergessen')) ?>">Passwort vergessen?</a></p>
        <p class="muted small">Noch kein Konto? <a href="<?= e(\Core\Shop::url('registrieren')) ?>">Jetzt registrieren</a></p>
    </div>
</div>
