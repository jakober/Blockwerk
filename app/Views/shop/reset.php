<div class="shop">
    <?= \Core\View::fetch('shop/_bar', []) ?>
    <h1 class="cms-heading">Neues Passwort vergeben</h1>
    <div class="shop-account-form">
        <form method="post" action="<?= e(\Core\Shop::url('passwort-neu/' . $token)) ?>" class="shop-checkout-form">
            <?= csrf_field() ?>
            <label>Neues Passwort (mindestens 6 Zeichen)<input type="password" name="password" required minlength="6" autofocus></label>
            <div class="shop-checkout-submit"><button type="submit" class="cms-button">Passwort speichern</button></div>
        </form>
    </div>
</div>
