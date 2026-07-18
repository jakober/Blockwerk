<?php
declare(strict_types=1);

namespace Core;

/**
 * Schlanker PayPal-REST-Client (Orders v2): Zugriffstoken holen, Bestellung
 * anlegen und Zahlung erfassen. Nutzt die in den Shop-Einstellungen
 * hinterlegten Zugangsdaten (Client-ID/Secret, Sandbox oder Live).
 */
class PayPal
{
    private static function token(): ?string
    {
        $id = Shop::paypalClientId();
        $secret = Shop::paypalSecret();
        if ($id === '' || $secret === '') {
            return null;
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => Shop::paypalApiBase() . '/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $id . ':' . $secret,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($res === false || $code !== 200) {
            return null;
        }
        $data = json_decode((string) $res, true);
        return is_array($data) ? ($data['access_token'] ?? null) : null;
    }

    /** @return array{0:?string,1:?string}  [orderId, error] */
    public static function createOrder(int $totalCents, string $currency, string $reference): array
    {
        $token = self::token();
        if ($token === null) {
            return [null, 'PayPal ist nicht korrekt konfiguriert.'];
        }
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $reference,
                'amount' => [
                    'currency_code' => $currency,
                    'value' => number_format($totalCents / 100, 2, '.', ''),
                ],
            ]],
        ];
        [$res, $code] = self::request('POST', '/v2/checkout/orders', $token, $payload);
        if ($res === null || ($code !== 200 && $code !== 201)) {
            return [null, 'PayPal-Bestellung konnte nicht angelegt werden.'];
        }
        return [$res['id'] ?? null, null];
    }

    /** @return array{0:bool,1:?string}  [erfolgreich, error] */
    public static function captureOrder(string $orderId): array
    {
        $token = self::token();
        if ($token === null) {
            return [false, 'PayPal ist nicht korrekt konfiguriert.'];
        }
        [$res, $code] = self::request('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', $token, []);
        if ($res === null || ($code !== 200 && $code !== 201)) {
            return [false, 'Zahlung konnte nicht bestätigt werden.'];
        }
        return [($res['status'] ?? '') === 'COMPLETED', null];
    }

    /** @return array{0:?array,1:int} [decodedResponse, httpCode] */
    private static function request(string $method, string $path, string $token, array $body): array
    {
        $ch = curl_init();
        $opts = [
            CURLOPT_URL => Shop::paypalApiBase() . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ];
        if ($body !== [] || $method === 'POST') {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body ?: new \stdClass(), JSON_UNESCAPED_UNICODE) ?: '{}';
        }
        curl_setopt_array($ch, $opts);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($res === false) {
            return [null, 0];
        }
        $data = json_decode((string) $res, true);
        return [is_array($data) ? $data : null, $code];
    }
}
