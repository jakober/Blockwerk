<?php
declare(strict_types=1);

namespace Core;

use Models\Setting;

/**
 * Client für den zentralen Blockwerk-Orange-KI-Dienst (ai-server/).
 * Alle Anfragen laufen mit dem Lizenzschlüssel der Installation über den
 * Dienst des Anbieters, der die Token-Abrechnung übernimmt.
 */
class Ai
{
    /** Standard-Dienst des Anbieters – Kunden brauchen nur den Lizenzschlüssel. */
    public const DEFAULT_SERVICE_URL = 'https://blockwerk-orange.de/ai-server';

    public static function serviceUrl(): string
    {
        $url = rtrim(trim(Setting::get('ai_service_url', '')), '/');
        return $url !== '' ? $url : self::DEFAULT_SERVICE_URL;
    }

    public static function licenseKey(): string
    {
        return trim(Setting::get('ai_license_key', ''));
    }

    /** Domain dieser Installation – meldet dem Anbieter-Dienst, wo das CMS läuft. */
    public static function domain(): string
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        return substr($host, 0, 255);
    }

    public static function configured(): bool
    {
        return self::serviceUrl() !== '' && self::licenseKey() !== '';
    }

    /**
     * Chat-Anfrage an Claude (über den Dienst). Liefert die rohe
     * Messages-API-Antwort inkl. "balance" (Restguthaben).
     *
     * @throws \RuntimeException bei Verbindungs-/Dienstfehlern
     */
    public static function chat(array $messages, array $tools, string $system, bool $fast = false): array
    {
        $body = [
            'messages' => $messages,
            'tools' => $tools,
            'system' => $system,
            'max_tokens' => 8000,
        ];
        if ($fast) {
            // Signal an den Dienst, für einfache Aufgaben das schnellere Modell zu nutzen.
            $body['fast'] = true;
        }
        return self::request('POST', '/v1/chat', $body, 240);
    }

    /** Bildgenerierung – liefert ['image_b64' => …, 'balance' => …]. */
    public static function image(string $prompt): array
    {
        return self::request('POST', '/v1/image', ['prompt' => $prompt], 300);
    }

    /** Guthaben-Abfrage – liefert ['balance' => …, 'name' => …]. */
    public static function balance(): array
    {
        return self::request('GET', '/v1/balance', []);
    }

    private static function request(string $method, string $path, array $body, int $timeout = 60): array
    {
        if (!self::configured()) {
            throw new \RuntimeException('Der KI-Assistent ist noch nicht eingerichtet – bitte Dienst-URL und Lizenzschlüssel in den Einstellungen hinterlegen.');
        }

        $url = self::serviceUrl() . $path;
        $ch = curl_init();
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
        ];
        if ($method === 'GET') {
            $url .= '?license_key=' . rawurlencode(self::licenseKey()) . '&domain=' . rawurlencode(self::domain());
        } else {
            $body['license_key'] = self::licenseKey();
            $body['domain'] = self::domain();
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE) ?: '';
            $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
        }
        $options[CURLOPT_URL] = $url;
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!is_string($response)) {
            throw new \RuntimeException('Der KI-Dienst ist nicht erreichbar: ' . $error);
        }
        $json = json_decode($response, true);
        if (!is_array($json)) {
            throw new \RuntimeException('Der KI-Dienst hat unerwartet geantwortet (HTTP ' . $status . ').');
        }
        if ($status >= 400) {
            $message = is_string($json['error'] ?? null) ? $json['error'] : 'Fehler des KI-Dienstes (HTTP ' . $status . ').';
            throw new \RuntimeException($message);
        }
        return $json;
    }
}
