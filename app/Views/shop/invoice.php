<?php
$fmt = static fn ($c) => \Core\Shop::formatPrice((int) $c);
$g = static fn (string $k): string => trim((string) \Models\Setting::get($k, ''));
$logo = \Core\Shop::invoiceLogo();
$company = \Core\Shop::invoiceName();
$address = $g('shop_invoice_address');
$invEmail = $g('shop_invoice_email');
$invPhone = $g('shop_invoice_phone');
$invWeb = $g('shop_invoice_website');
$tax = $g('shop_invoice_tax');
$bank = $g('shop_invoice_bank');
$note = $g('shop_invoice_note');
$recipient = trim(((string) ($order['first_name'] ?? '')) . ' ' . ((string) ($order['last_name'] ?? '')));
$mode = $mode ?? 'page';
$isEmail = $mode === 'email';
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rechnung <?= e($order['number']) ?></title>
<style>
    * { box-sizing: border-box; }
    body { font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #1f2937; margin: 0; background: #f3f4f6; }
    /* Blattformat A4 (210 × 297 mm) – die Vorschau hat immer die Proportionen einer A4-Seite. */
    .sheet { background: #fff; width: 210mm; max-width: 100%; <?= $isEmail ? '' : 'min-height: 297mm;' ?> margin: 20px auto; padding: 22mm 20mm; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
    .inv-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 40px; }
    .inv-logo img { max-height: 80px; max-width: 260px; }
    .inv-logo .inv-fallback { font-size: 22px; font-weight: 800; }
    .inv-sender { text-align: right; font-size: 12.5px; line-height: 1.5; color: #374151; white-space: pre-line; }
    .inv-sender strong { display: block; font-size: 14px; color: #111827; }
    .inv-parties { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 32px; }
    .inv-to { font-size: 14px; line-height: 1.55; white-space: pre-line; }
    .inv-to .label { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; margin-bottom: 6px; }
    .inv-meta { text-align: right; font-size: 13px; line-height: 1.7; }
    .inv-meta .big { font-size: 24px; font-weight: 800; letter-spacing: -.4px; margin-bottom: 6px; }
    table.inv-items { width: 100%; border-collapse: collapse; margin: 8px 0 0; font-size: 13.5px; }
    table.inv-items th { text-align: left; border-bottom: 2px solid #111827; padding: 8px 6px; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }
    table.inv-items td { padding: 9px 6px; border-bottom: 1px solid #e5e7eb; }
    .ta-r { text-align: right; }
    .inv-totals { margin-top: 14px; margin-left: auto; width: 300px; font-size: 14px; }
    .inv-totals div { display: flex; justify-content: space-between; padding: 5px 6px; }
    .inv-totals .grand { border-top: 2px solid #111827; font-weight: 800; font-size: 16px; margin-top: 4px; }
    .inv-foot { margin-top: 40px; font-size: 12.5px; color: #374151; line-height: 1.6; }
    .inv-foot h4 { margin: 0 0 4px; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; }
    .inv-foot .block { margin-bottom: 16px; white-space: pre-line; }
    .inv-actions { max-width: 800px; margin: 20px auto 0; text-align: right; }
    .inv-actions button { font: inherit; font-weight: 600; padding: 10px 20px; border: 0; border-radius: 8px; background: #ea580c; color: #fff; cursor: pointer; }
    @media print {
        body { background: #fff; }
        .sheet { box-shadow: none; margin: 0; width: auto; max-width: none; min-height: 0; padding: 0; }
        .inv-actions { display: none; }
        @page { margin: 18mm; }
    }
</style>
</head>
<body>
<?php if (!$isEmail): ?><div class="inv-actions"><button type="button" onclick="window.print()">Drucken / als PDF speichern</button></div><?php endif; ?>
<div class="sheet">
    <div class="inv-top">
        <div class="inv-logo">
            <?php if ($logo !== ''): ?>
                <img src="<?= e($logo) ?>" alt="<?= e($company) ?>">
            <?php else: ?>
                <span class="inv-fallback"><?= e($company) ?></span>
            <?php endif; ?>
        </div>
        <div class="inv-sender"><strong><?= e($company) ?></strong><?php
            $senderLines = array_filter([$address, $tax !== '' ? 'Steuer-Nr.: ' . $tax : '', $invPhone !== '' ? 'Tel.: ' . $invPhone : '', $invEmail, $invWeb]);
            echo e(implode("\n", $senderLines));
        ?></div>
    </div>

    <div class="inv-parties">
        <div class="inv-to">
            <div class="label">Rechnung an</div>
            <?php
            $toLines = array_filter([
                $recipient,
                (string) ($order['company'] ?? ''),
                (string) ($order['street'] ?? ''),
                trim(((string) ($order['zip'] ?? '')) . ' ' . ((string) ($order['city'] ?? ''))),
                (string) ($order['country'] ?? ''),
            ], static fn ($l): bool => trim((string) $l) !== '');
            echo e(implode("\n", $toLines));
            ?>
        </div>
        <div class="inv-meta">
            <div class="big">Rechnung</div>
            Nr.: <strong><?= e($order['number']) ?></strong><br>
            Datum: <?= e(format_date_de($order['created_at'])) ?><br>
            Zahlung: <?= e($order['payment_method'] ?? '–') ?>
        </div>
    </div>

    <table class="inv-items">
        <thead><tr><th>Pos.</th><th>Bezeichnung</th><th class="ta-r">Menge</th><th class="ta-r">Einzelpreis</th><th class="ta-r">Summe</th></tr></thead>
        <tbody>
            <?php foreach ($items as $i => $it): ?>
                <tr>
                    <td><?= (int) $i + 1 ?></td>
                    <td><?= e($it['name']) ?><?php if (!empty($it['sku'])): ?> <span style="color:#9ca3af">· <?= e($it['sku']) ?></span><?php endif; ?></td>
                    <td class="ta-r"><?= (int) $it['qty'] ?></td>
                    <td class="ta-r"><?= e($fmt($it['price'])) ?></td>
                    <td class="ta-r"><?= e($fmt((int) $it['price'] * (int) $it['qty'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="inv-totals">
        <div><span>Zwischensumme</span><span><?= e($fmt($order['subtotal'])) ?></span></div>
        <div><span>Versand<?= !empty($order['shipping_method']) ? ' (' . e($order['shipping_method']) . ')' : '' ?></span><span><?= e($fmt($order['shipping_cost'])) ?></span></div>
        <div class="grand"><span>Gesamtbetrag</span><span><?= e($fmt($order['total'])) ?></span></div>
    </div>

    <div class="inv-foot">
        <?php if ($note !== ''): ?><div class="block"><?= e($note) ?></div><?php endif; ?>
        <?php if ($bank !== ''): ?><div class="block"><h4>Bankverbindung</h4><?= e($bank) ?></div><?php endif; ?>
    </div>
</div>
</body>
</html>
