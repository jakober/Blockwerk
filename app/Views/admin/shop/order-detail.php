<?php
$statusLabels = \Models\ShopOrder::STATUS_LABELS;
$fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c);
?>
<div class="page-actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <a class="btn btn-ghost" href="<?= e(url('/admin/shop/orders')) ?>">← Alle Bestellungen</a>
    <?php if (empty($invoice)): ?>
        <form method="post" action="<?= e(url('/admin/shop/orders/' . $order['id'] . '/invoice/create')) ?>" class="inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">🧾 Rechnung erstellen (PDF)</button>
        </form>
        <span class="muted small">Es wird eine fortlaufende Rechnungsnummer vergeben.</span>
    <?php else: ?>
        <span class="badge badge-green">Rechnung <?= e($invoice['number']) ?></span>
        <a class="btn" href="<?= e(url('/admin/shop/orders/' . $order['id'] . '/invoice')) ?>" target="_blank" rel="noopener">🧾 PDF ansehen</a>
        <form method="post" action="<?= e(url('/admin/shop/orders/' . $order['id'] . '/invoice-mail')) ?>" class="inline" data-confirm="Rechnung „<?= e($invoice['number']) ?>“ als PDF an <?= e($order['email']) ?> senden?" data-confirm-ok="Senden">
            <?= csrf_field() ?>
            <button type="submit" class="btn">✉️ Rechnung per E-Mail senden</button>
        </form>
    <?php endif; ?>
</div>

<div class="editor-grid">
    <div>
        <div class="card">
            <h2>Bestellung <?= e($order['number']) ?></h2>
            <p class="muted small"><?= e(format_date_de($order['created_at'], true)) ?></p>
            <table class="table">
                <thead><tr><th>Artikel</th><th>Einzelpreis</th><th>Menge</th><th>Summe</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><strong><?= e($it['name']) ?></strong><?php if (!empty($it['sku'])): ?> <span class="muted small">· <?= e($it['sku']) ?></span><?php endif; ?></td>
                            <td><?= e($fmt($it['price'])) ?></td>
                            <td><?= (int) $it['qty'] ?></td>
                            <td><?= e($fmt((int) $it['price'] * (int) $it['qty'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="3" style="text-align:right">Zwischensumme</td><td><?= e($fmt($order['subtotal'])) ?></td></tr>
                    <tr><td colspan="3" style="text-align:right">Versand (<?= e($order['shipping_method'] ?? '–') ?>)</td><td><?= e($fmt($order['shipping_cost'])) ?></td></tr>
                    <tr><td colspan="3" style="text-align:right"><strong>Gesamt</strong></td><td><strong><?= e($fmt($order['total'])) ?></strong></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <aside class="card">
        <h3>Status</h3>
        <form method="post" action="<?= e(url('/admin/shop/orders/' . $order['id'] . '/status')) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <select name="status">
                    <?php foreach (['new', 'paid', 'shipped', 'cancelled'] as $k): ?>
                        <option value="<?= $k ?>" <?= $order['status'] === $k ? 'selected' : '' ?>><?= e($statusLabels[$k]) ?></option>
                    <?php endforeach; ?>
                    <?php if ($order['status'] === 'refunded'): ?>
                        <option value="refunded" selected disabled><?= e($statusLabels['refunded']) ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <?php if (in_array($order['status'], ['new', 'paid'], true)): ?>
                <div class="form-group">
                    <label for="tracking_number">Sendungsnummer (optional)</label>
                    <input type="text" id="tracking_number" name="tracking_number" value="<?= e($order['tracking_number'] ?? '') ?>" placeholder="z. B. DHL-Trackingnummer">
                </div>
                <div class="form-group">
                    <label for="tracking_url">Tracking-Link (optional)</label>
                    <input type="text" id="tracking_url" name="tracking_url" value="<?= e($order['tracking_url'] ?? '') ?>" placeholder="https://…">
                </div>
            <?php endif; ?>
            <label class="checkbox-group" style="font-size:13px"><input type="checkbox" name="no_mail" value="1"> Kunde nicht per E-Mail benachrichtigen</label>
            <p class="muted small" style="margin:4px 0 8px">Bei „Bezahlt", „Versendet" oder „Storniert" erhält der Kunde standardmäßig eine E-Mail mit dem neuen Status – bei „Versendet" inklusive Sendungsnummer, falls hinterlegt.</p>
            <button type="submit" class="btn btn-primary btn-small">Status setzen</button>
        </form>

        <?php if ($order['status'] !== 'refunded'): ?>
            <h3 style="margin-top:20px">Rückerstattung</h3>
            <form method="post" action="<?= e(url('/admin/shop/orders/' . $order['id'] . '/refund')) ?>" data-confirm="Bestellung „<?= e($order['number']) ?>“ erstatten?<?= ($order['payment_method'] ?? '') === 'paypal' ? ' Der Betrag wird über PayPal zurückgebucht.' : '' ?>" data-confirm-ok="Erstatten">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="refund_amount">Betrag (leer = voller Bestellwert <?= e($fmt($order['total'])) ?>)</label>
                    <input type="text" id="refund_amount" name="amount" placeholder="<?= e($fmt($order['total'])) ?>" inputmode="decimal">
                </div>
                <p class="muted small" style="margin:4px 0 8px">
                    <?= ($order['payment_method'] ?? '') === 'paypal' && !empty($order['paypal_order_id'])
                        ? 'Wird über PayPal zurückgebucht, Lagerbestand wird zurückgebucht und der Kunde per E-Mail informiert.'
                        : 'Nur Status-Änderung – die Rückzahlung (Überweisung) erfolgt außerhalb des Systems. Lagerbestand wird zurückgebucht.' ?>
                </p>
                <button type="submit" class="btn btn-small btn-danger">Als erstattet markieren</button>
            </form>
        <?php endif; ?>

        <h3 style="margin-top:20px">Zahlung</h3>
        <p class="small"><?= e($order['payment_method'] ?? '–') ?> ·
            <?= $order['payment_status'] === 'paid' ? '<span class="badge badge-green">bezahlt</span>' : '<span class="badge badge-amber">offen</span>' ?>
            <?php if (!empty($order['paypal_order_id'])): ?><br><span class="muted small">PayPal: <?= e($order['paypal_order_id']) ?></span><?php endif; ?>
        </p>
        <?php if (!empty($order['tracking_number'])): ?>
            <h3 style="margin-top:20px">Versand</h3>
            <p class="small">Sendungsnummer: <?= e($order['tracking_number']) ?>
                <?php if (!empty($order['tracking_url'])): ?><br><a href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener">Sendung verfolgen</a><?php endif; ?>
            </p>
        <?php endif; ?>

        <h3 style="margin-top:20px">Kunde</h3>
        <p class="small">
            <?= e(trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))) ?><br>
            <?php if (!empty($order['company'])): ?><?= e($order['company']) ?><br><?php endif; ?>
            <?= e($order['street'] ?? '') ?><br>
            <?= e(trim(($order['zip'] ?? '') . ' ' . ($order['city'] ?? ''))) ?><br>
            <?= e($order['country'] ?? '') ?><br>
            <a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a>
            <?php if (!empty($order['phone'])): ?><br><?= e($order['phone']) ?><?php endif; ?>
        </p>
        <?php if (!empty($order['note'])): ?>
            <h3 style="margin-top:20px">Anmerkung</h3>
            <p class="small"><?= nl2br(e($order['note'])) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/admin/shop/orders/' . $order['id'] . '/delete')) ?>" class="inline" data-confirm="Bestellung endgültig löschen?" data-confirm-danger data-confirm-ok="Löschen" style="margin-top:24px">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-small btn-danger">Bestellung löschen</button>
        </form>
    </aside>
</div>
