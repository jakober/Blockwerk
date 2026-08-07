<?php
declare(strict_types=1);

namespace Core;

use Models\Setting;

/**
 * Baut die Rechnung als PDF (A4) über den abhängigkeitsfreien Core\Pdf.
 * Verwendet die Rechnungs-Kontaktdaten aus den Shop-Einstellungen, die
 * fortlaufende Rechnungsnummer aus $invoice und die Bestelldaten.
 */
class InvoicePdf
{
    private const L = 40.0;   // linker Rand
    private const R = 555.0;  // rechter Rand

    public static function render(array $order, array $items, array $invoice): string
    {
        $pdf = new Pdf();
        $g = static fn (string $k): string => trim((string) Setting::get($k, ''));
        $fmt = static fn ($c): string => Shop::formatPrice((int) $c);
        $ink = [17, 24, 39];
        $muted = [107, 114, 128];
        $accent = [234, 88, 12];     // Blockwerk Orange (#ea580c)
        $accentBg = [255, 237, 213]; // helles Orange (#ffedd5)

        // --- Oranger Akzentbalken am oberen Rand ---
        $pdf->rect(0, 0, 595.28, 6, $accent);

        // --- Kopf: Logo/Firma links, Absender rechts ---
        $company = Shop::invoiceName();
        $logo = self::logoJpeg();
        $topY = 55.0;
        if ($logo !== null) {
            $maxW = 190.0;
            $maxH = 60.0;
            $ratio = $logo['w'] / max(1, $logo['h']);
            $w = $maxW;
            $h = $w / $ratio;
            if ($h > $maxH) { $h = $maxH; $w = $h * $ratio; }
            $pdf->image($logo['data'], self::L, $topY - 10, $w, $h);
        } else {
            // Fallback: gezeichnete Blockwerk-Marke + Wortmarke (statt nur Text).
            $mark = self::brandMarkJpeg();
            $textX = self::L;
            if ($mark !== null) {
                $pdf->image($mark['data'], self::L, $topY - 22, 34, 34);
                $textX = self::L + 44;
            }
            $pdf->text($textX, $topY, $company, 20, true, $ink);
        }

        $sender = array_merge(
            [$company],
            explode("\n", $g('shop_invoice_address')),
            [
                $g('shop_invoice_tax') !== '' ? 'Steuer-Nr.: ' . $g('shop_invoice_tax') : '',
                $g('shop_invoice_phone') !== '' ? 'Tel.: ' . $g('shop_invoice_phone') : '',
                $g('shop_invoice_email'),
                $g('shop_invoice_website'),
            ]
        );
        $senderLines = array_values(array_filter($sender, static fn ($l) => trim((string) $l) !== ''));
        $sy = 40.0;
        foreach (self::wrapLines($senderLines) as $i => $line) {
            $pdf->textRight(self::R, $sy, $line, $i === 0 ? 11 : 9, $i === 0, $i === 0 ? $ink : $muted);
            $sy += $i === 0 ? 15 : 12.5;
        }

        // --- Titel + Meta ---
        $isCredit = ($invoice['type'] ?? 'invoice') === 'credit';
        $pdf->text(self::L, 150, $isCredit ? 'GUTSCHRIFT' : 'RECHNUNG', 26, true, $accent);

        $metaY = 130.0;
        $meta = [
            [$isCredit ? 'Gutschrift-Nr.' : 'Rechnungs-Nr.', (string) ($invoice['number'] ?? '')],
            ['Datum', format_date_de((string) ($invoice['created_at'] ?? date('Y-m-d')))],
        ];
        if ($isCredit && !empty($invoice['ref_number'])) {
            $meta[] = ['zu Rechnung', (string) $invoice['ref_number']];
        } else {
            $meta[] = ['Bestell-Nr.', (string) ($order['number'] ?? '')];
            $meta[] = ['Zahlung', (string) ($order['payment_method'] ?? '–')];
        }
        foreach ($meta as [$label, $val]) {
            $pdf->textRight(self::R - 110, $metaY, $label . ':', 10, false, $muted);
            $pdf->textRight(self::R, $metaY, $val, 10, true, $ink);
            $metaY += 16;
        }

        // --- Empfänger ---
        $ry = 210.0;
        $pdf->text(self::L, $ry, $isCredit ? 'GUTSCHRIFT AN' : 'RECHNUNG AN', 9, true, $muted);
        $ry += 18;
        $recipient = trim(((string) ($order['first_name'] ?? '')) . ' ' . ((string) ($order['last_name'] ?? '')));
        $toLines = array_values(array_filter([
            $recipient,
            (string) ($order['company'] ?? ''),
            (string) ($order['street'] ?? ''),
            trim(((string) ($order['zip'] ?? '')) . ' ' . ((string) ($order['city'] ?? ''))),
            (string) ($order['country'] ?? ''),
        ], static fn ($l) => trim((string) $l) !== ''));
        foreach ($toLines as $line) {
            $pdf->text(self::L, $ry, $line, 11, false, $ink);
            $ry += 15;
        }

        // --- Artikel-Tabelle ---
        $y = max($ry, $metaY) + 24;
        $colDesc = 74.0;
        $colQty = 372.0;   // rechtsbündig
        $colUnit = 468.0;  // rechtsbündig
        $colSum = self::R; // rechtsbündig

        $pdf->rect(self::L, $y - 12, self::R - self::L, 22, $accentBg);
        $pdf->text(self::L + 6, $y + 3, 'Pos', 9, true, $ink);
        $pdf->text($colDesc, $y + 3, 'Bezeichnung', 9, true, $ink);
        $pdf->textRight($colQty, $y + 3, 'Menge', 9, true, $ink);
        $pdf->textRight($colUnit, $y + 3, 'Einzelpreis', 9, true, $ink);
        $pdf->textRight($colSum, $y + 3, 'Summe', 9, true, $ink);
        $y += 24;

        foreach ($items as $i => $it) {
            if ($y > 720) {
                $pdf->addPage();
                $y = 60;
            }
            $name = (string) ($it['name'] ?? '');
            if (!empty($it['sku'])) {
                $name .= '  ·  ' . $it['sku'];
            }
            $name = self::fit($pdf, $name, $colQty - $colDesc - 60, 10);
            $pdf->text(self::L + 6, $y, (string) ((int) $i + 1), 10, false, $ink);
            $pdf->text($colDesc, $y, $name, 10, false, $ink);
            $pdf->textRight($colQty, $y, (string) (int) $it['qty'], 10, false, $ink);
            $pdf->textRight($colUnit, $y, $fmt($it['price']), 10, false, $ink);
            $pdf->textRight($colSum, $y, $fmt((int) $it['price'] * (int) $it['qty']), 10, false, $ink);
            $y += 8;
            $pdf->line(self::L, $y, self::R, $y, 0.4, [229, 231, 235]);
            $y += 14;
        }

        // --- Summen ---
        // Der Block bekommt eine ruhige Fläche, damit der Gesamtbetrag sofort
        // ins Auge fällt. Zeilen ohne Aussage (Versand 0,00 € bei einer reinen
        // Dienstleistung) werden weggelassen statt mit „0,00" gefüllt.
        $y += 14;
        $labelX = self::R - 190;
        $rows = [['Zwischensumme', $fmt($order['subtotal'])]];
        if ((int) ($order['shipping_cost'] ?? 0) > 0) {
            $rows[] = ['Versand' . (!empty($order['shipping_method']) ? ' (' . $order['shipping_method'] . ')' : ''), $fmt($order['shipping_cost'])];
        }
        // Enthaltene MwSt. je Satz ausweisen (Bruttopreise) – gruppiert nach den zum
        // Bestellzeitpunkt eingefrorenen Steuersätzen der Positionen. Versand wird
        // vereinfachend mit dem Standardsatz besteuert.
        if (!$isCredit && Shop::taxMode() === 'inclusive') {
            $taxGroups = [];
            foreach ($items as $it) {
                $rate = ($it['tax_rate'] ?? null) !== null && $it['tax_rate'] !== '' ? (float) $it['tax_rate'] : Shop::defaultTaxRate();
                $lineGross = (int) $it['price'] * (int) $it['qty'];
                $taxGroups[$rate] = ($taxGroups[$rate] ?? 0) + Shop::taxAmount($lineGross, $rate);
            }
            if ((int) ($order['shipping_cost'] ?? 0) > 0) {
                $shipRate = Shop::defaultTaxRate();
                $taxGroups[$shipRate] = ($taxGroups[$shipRate] ?? 0) + Shop::taxAmount((int) $order['shipping_cost'], $shipRate);
            }
            ksort($taxGroups);
            foreach ($taxGroups as $rate => $amount) {
                if ($amount <= 0) {
                    continue;
                }
                $rateLabel = rtrim(rtrim(number_format((float) $rate, 2, ',', ''), '0'), ',');
                $rows[] = ['enthaltene MwSt. (' . $rateLabel . ' %)', $fmt($amount)];
            }
        }
        $blockH = count($rows) * 16 + 40;
        $pdf->rect($labelX - 14, $y - 14, self::R - $labelX + 14, $blockH, [248, 250, 252]);
        foreach ($rows as [$label, $val]) {
            $pdf->text($labelX, $y, $label, 10, false, $muted);
            $pdf->textRight(self::R, $y, $val, 10, false, $ink);
            $y += 16;
        }
        $pdf->line($labelX, $y + 2, self::R, $y + 2, 1, $accent);
        $y += 18;
        $pdf->text($labelX, $y, $isCredit ? 'Gutschriftbetrag' : 'Gesamtbetrag', 12.5, true, $ink);
        $pdf->textRight(self::R, $y, $fmt($order['total']), 13.5, true, $accent);
        $y += 20;
        if ($isCredit) {
            $pdf->textRight(self::R, $y, 'Der Betrag wird dir erstattet.', 9, false, $muted);
            $y += 14;
        }
        // Bezahlt-Vermerk – erspart die Frage „muss ich noch überweisen?".
        if (!$isCredit && ($order['payment_status'] ?? '') === 'paid') {
            $pdf->textRight(self::R, $y, 'Betrag bezahlt – bitte nicht überweisen.', 9, false, $muted);
            $y += 14;
        }
        $y += 22;

        // --- Grund der Gutschrift ---
        if ($isCredit && trim((string) ($invoice['note'] ?? '')) !== '') {
            foreach (self::wrapLines(explode("\n", (string) $invoice['note'])) as $line) {
                if ($y > 800) { $pdf->addPage(); $y = 60; }
                $pdf->text(self::L, $y, $line, 10, false, $ink);
                $y += 14;
            }
            $y += 10;
        }

        // --- Fußtext + Bank ---
        $note = $g('shop_invoice_note');
        if ($note !== '') {
            foreach (self::wrapLines(explode("\n", $note)) as $line) {
                if ($y > 800) { $pdf->addPage(); $y = 60; }
                $pdf->text(self::L, $y, $line, 9.5, false, $muted);
                $y += 13;
            }
            $y += 8;
        }
        $bank = $g('shop_invoice_bank');
        if ($bank !== '') {
            // Steht die Überschrift schon im hinterlegten Text, nicht doppeln.
            if (stripos(ltrim($bank), 'bankverbindung') !== 0) {
                $pdf->text(self::L, $y, 'BANKVERBINDUNG', 8.5, true, $muted);
                $y += 13;
            }
            foreach (self::wrapLines(explode("\n", $bank)) as $line) {
                if ($y > 800) { $pdf->addPage(); $y = 60; }
                $pdf->text(self::L, $y, $line, 9.5, false, $ink);
                $y += 13;
            }
        }

        return $pdf->output();
    }

    /** Dateiname für den PDF-Download. */
    public static function filename(array $invoice): string
    {
        $num = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($invoice['number'] ?? 'Rechnung'));
        $label = ($invoice['type'] ?? 'invoice') === 'credit' ? 'Gutschrift' : 'Rechnung';
        return $label . '-' . ($num !== '' ? $num : 'Beleg') . '.pdf';
    }

    /** @return array{data:string,w:int,h:int}|null JPEG-Daten des Logos (via GD konvertiert). */
    private static function logoJpeg(): ?array
    {
        $logo = Shop::invoiceLogo();
        if ($logo === '' || !function_exists('imagecreatefromstring')) {
            return null;
        }
        $bin = self::readImage($logo);
        if ($bin === null) {
            return null;
        }
        try {
            $im = @imagecreatefromstring($bin);
            if ($im === false) {
                return null;
            }
            $w = imagesx($im);
            $h = imagesy($im);
            // Transparenz auf Weiß setzen (JPEG kennt kein Alpha).
            $flat = imagecreatetruecolor($w, $h);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $im, 0, 0, 0, 0, $w, $h);
            ob_start();
            imagejpeg($flat, null, 90);
            $jpeg = (string) ob_get_clean();
            imagedestroy($im);
            imagedestroy($flat);
            return $jpeg !== '' ? ['data' => $jpeg, 'w' => $w, 'h' => $h] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Zeichnet die Blockwerk-Marke (vier Blöcke, drei orange + ein Akzent) per GD
     * und liefert sie als JPEG – Fallback-Logo, wenn der Betreiber keins gesetzt hat.
     * @return array{data:string,w:int,h:int}|null
     */
    private static function brandMarkJpeg(): ?array
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        try {
            $s = 200; // hoch aufgelöst, im PDF verkleinert
            $im = imagecreatetruecolor($s, $s);
            imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
            $orange = imagecolorallocate($im, 234, 88, 12);   // #ea580c
            $amber = imagecolorallocate($im, 251, 191, 36);   // #fbbf24
            // Raster wie logo.svg (viewBox 48): x,y,größe skaliert auf 200.
            $k = $s / 48.0;
            $box = static function (array $r) use ($im, $k) {
                imagefilledrectangle($im, (int) ($r[0] * $k), (int) ($r[1] * $k), (int) (($r[0] + $r[2]) * $k), (int) (($r[1] + $r[2]) * $k), $r[3]);
            };
            $box([4, 4, 18, $orange]);
            $box([4, 26, 18, $orange]);
            $box([26, 26, 18, $orange]);
            $box([28, 2, 18, $amber]);
            ob_start();
            imagejpeg($im, null, 92);
            $jpeg = (string) ob_get_clean();
            imagedestroy($im);
            return $jpeg !== '' ? ['data' => $jpeg, 'w' => $s, 'h' => $s] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Bilddaten aus lokalem uploads-Pfad (oder Daten-URL) laden – nie remote. */
    private static function readImage(string $ref): ?string
    {
        if (str_starts_with($ref, 'data:')) {
            $comma = strpos($ref, ',');
            return $comma !== false ? (base64_decode(substr($ref, $comma + 1)) ?: null) : null;
        }
        $path = parse_url($ref, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $ref;
        }
        $pos = strpos($path, 'uploads/');
        if ($pos === false) {
            return null;
        }
        $file = BASE_PATH . '/public/' . substr($path, $pos);
        $real = realpath($file);
        $root = realpath(BASE_PATH . '/public/uploads');
        if ($real === false || $root === false || !str_starts_with($real, $root)) {
            return null;
        }
        return @file_get_contents($real) ?: null;
    }

    /** Zeilen kürzen, damit sie nicht über den Rand laufen. */
    /**
     * Zeilen auf Seitenbreite umbrechen. Früher wurde nach 70 Zeichen hart
     * abgeschnitten – längere Hinweise (z. B. der Kleinunternehmer-Hinweis)
     * verloren dadurch ihr Ende.
     */
    private static function wrapLines(array $lines, int $max = 70): array
    {
        $out = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (mb_strlen($line) <= $max) {
                $out[] = $line;
                continue;
            }
            $current = '';
            foreach (preg_split('/\s+/', $line) ?: [] as $word) {
                // Einzelnes überlanges Wort (z. B. IBAN ohne Leerzeichen) hart trennen.
                while (mb_strlen($word) > $max) {
                    if ($current !== '') {
                        $out[] = $current;
                        $current = '';
                    }
                    $out[] = mb_substr($word, 0, $max);
                    $word = mb_substr($word, $max);
                }
                if ($current === '') {
                    $current = $word;
                } elseif (mb_strlen($current) + 1 + mb_strlen($word) <= $max) {
                    $current .= ' ' . $word;
                } else {
                    $out[] = $current;
                    $current = $word;
                }
            }
            if ($current !== '') {
                $out[] = $current;
            }
        }
        return $out;
    }

    /** Text auf eine maximale Pixelbreite kürzen (…). */
    private static function fit(Pdf $pdf, string $s, float $maxW, float $size): string
    {
        if ($pdf->width($s, $size, false) <= $maxW) {
            return $s;
        }
        while ($s !== '' && $pdf->width($s . '…', $size, false) > $maxW) {
            $s = mb_substr($s, 0, mb_strlen($s) - 1);
        }
        return $s . '…';
    }
}
