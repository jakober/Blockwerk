<?php
declare(strict_types=1);

namespace Core;

/**
 * SEPA-Lastschrift-Sammeldatei (pain.008.001.02, "SEPA Core Direct Debit
 * Initiation") ohne Zahlungsdienstleister – wird direkt beim Online-Banking/
 * an die Hausbank übergeben. pain.008.001.02 ist die am weitesten verbreitete,
 * von praktisch allen deutschen Banken akzeptierte Version; verlangt eine Bank
 * ausdrücklich eine neuere Version (z. B. pain.008.001.08), muss diese Klasse
 * angepasst werden.
 */
class SepaXml
{
    /**
     * @param array $orders Bestellungen (aus shop_orders) mit sepa_* Feldern
     * @param string $collectionDate Fälligkeitsdatum (Y-m-d)
     */
    public static function build(array $orders, string $collectionDate): string
    {
        $creditorName = self::safe(Shop::sepaCreditorName());
        $creditorId = Shop::sepaCreditorId();
        $creditorIban = Shop::sepaIban();
        $creditorBic = Shop::sepaBic();

        $now = date('Y-m-d\TH:i:s');
        $msgId = 'MSG-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $pmtInfId = 'PMT-' . date('YmdHis');

        $totalCents = 0;
        $txs = '';
        foreach ($orders as $o) {
            $amountCents = (int) $o['total'];
            $totalCents += $amountCents;
            $debtorBic = trim((string) ($o['sepa_bic'] ?? ''));
            $txs .= '<DrctDbtTxInf>'
                . '<PmtId><EndToEndId>' . self::safe((string) $o['number']) . '</EndToEndId></PmtId>'
                . '<InstdAmt Ccy="EUR">' . number_format($amountCents / 100, 2, '.', '') . '</InstdAmt>'
                . '<DrctDbtTx><MndtRltdInf>'
                . '<MndtId>' . self::safe((string) $o['sepa_mandate_ref']) . '</MndtId>'
                . '<DtOfSgntr>' . e((string) $o['sepa_mandate_date']) . '</DtOfSgntr>'
                . '</MndtRltdInf></DrctDbtTx>'
                . '<DbtrAgt><FinInstnId>' . ($debtorBic !== '' ? '<BIC>' . e($debtorBic) . '</BIC>' : '<Othr><Id>NOTPROVIDED</Id></Othr>') . '</FinInstnId></DbtrAgt>'
                . '<Dbtr><Nm>' . self::safe((string) $o['sepa_account_holder']) . '</Nm></Dbtr>'
                . '<DbtrAcct><Id><IBAN>' . e(Iban::normalize((string) $o['sepa_iban'])) . '</IBAN></Id></DbtrAcct>'
                . '<RmtInf><Ustrd>' . self::safe('Bestellung ' . $o['number']) . '</Ustrd></RmtInf>'
                . '</DrctDbtTxInf>';
        }
        $count = count($orders);
        $ctrlSum = number_format($totalCents / 100, 2, '.', '');

        $creditorAgt = $creditorBic !== ''
            ? '<CdtrAgt><FinInstnId><BIC>' . e($creditorBic) . '</BIC></FinInstnId></CdtrAgt>'
            : '';

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.008.001.02" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<CstmrDrctDbtInitn>'
            . '<GrpHdr>'
            . '<MsgId>' . e($msgId) . '</MsgId>'
            . '<CreDtTm>' . e($now) . '</CreDtTm>'
            . '<NbOfTxs>' . $count . '</NbOfTxs>'
            . '<CtrlSum>' . $ctrlSum . '</CtrlSum>'
            . '<InitgPty><Nm>' . $creditorName . '</Nm></InitgPty>'
            . '</GrpHdr>'
            . '<PmtInf>'
            . '<PmtInfId>' . e($pmtInfId) . '</PmtInfId>'
            . '<PmtMtd>DD</PmtMtd>'
            . '<NbOfTxs>' . $count . '</NbOfTxs>'
            . '<CtrlSum>' . $ctrlSum . '</CtrlSum>'
            . '<PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>CORE</Cd></LclInstrm><SeqTp>OOFF</SeqTp></PmtTpInf>'
            . '<ReqdColltnDt>' . e($collectionDate) . '</ReqdColltnDt>'
            . '<Cdtr><Nm>' . $creditorName . '</Nm></Cdtr>'
            . '<CdtrAcct><Id><IBAN>' . e($creditorIban) . '</IBAN></Id></CdtrAcct>'
            . $creditorAgt
            . '<ChrgBr>SLEV</ChrgBr>'
            . '<CdtrSchmeId><Id><PrvtId><Othr><Id>' . e($creditorId) . '</Id><SchmeNm><Prtry>SEPA</Prtry></SchmeNm></Othr></PrvtId></Id></CdtrSchmeId>'
            . $txs
            . '</PmtInf>'
            . '</CstmrDrctDbtInitn>'
            . '</Document>';
    }

    /** SEPA-Zeichensatz: Umlaute transliterieren, unzulässige Zeichen entfernen, dann XML-escapen. */
    private static function safe(string $s): string
    {
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']);
        $s = preg_replace('/[^A-Za-z0-9\/\-\?:\(\)\.,\'\+ ]/', '', $s) ?? '';
        return e(trim($s));
    }
}
