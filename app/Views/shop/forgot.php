<div class="shop">
    <?= \Core\View::fetch('shop/_bar', []) ?>
    <h1 class="cms-heading">Passwort vergessen</h1>
    <div class="shop-account-form">
        <p class="muted small">Gib deine E-Mail-Adresse ein – wir senden dir einen Link zum Zurücksetzen des Passworts.</p>
        <form method="post" action="<?= e(\Core\Shop::url('passwort-vergessen')) ?>" class="shop-checkout-form">
            <?= csrf_field() ?>
            <label>E-Mail<input type="email" name="email" required autofocus></label>
            <div class="shop-checkout-submit"><button type="submit" class="cms-button">Link senden</button></div>
        </form>
        <p class="muted small"><a href="<?= e(\Core\Shop::url('login')) ?>">Zurück zur Anmeldung</a></p>
    </div>
</div>
