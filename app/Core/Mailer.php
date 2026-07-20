<?php
declare(strict_types=1);

namespace Core;

use Models\Setting;

/**
 * E-Mail-Versand. Standard: PHP mail() über den Mailserver des Hosters.
 * Optional (Einstellungen → E-Mail-Versand): eigener SMTP-Server mit
 * SSL/STARTTLS und Anmeldung – implementiert als schlanker SMTP-Client
 * ohne Abhängigkeiten.
 */
class Mailer
{
    /**
     * Sendet eine E-Mail. Rückgabe: null bei Erfolg, sonst Fehlermeldung.
     * Wird $html gesetzt, geht die Mail als multipart/alternative raus (Text +
     * HTML) – der Text dient als Fallback für Clients ohne HTML.
     */
    public static function send(string $to, string $subject, string $body, ?string $replyTo = null, ?string $html = null, ?array $attachments = null): ?string
    {
        if (Setting::get('mail_transport', 'mail') === 'smtp') {
            return self::sendSmtp($to, $subject, $body, $replyTo, $html, $attachments);
        }
        return self::sendPhpMail($to, $subject, $body, $replyTo, $html, $attachments);
    }

    public static function fromAddress(): string
    {
        $from = Setting::get('mail_from', '');
        if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }
        $host = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
        $host = preg_replace('/[^a-z0-9.\-]/i', '', $host) ?: 'localhost';
        return 'noreply@' . $host;
    }

    public static function fromName(): string
    {
        $name = trim(Setting::get('mail_from_name', ''));
        return $name !== '' ? $name : Setting::get('site_name', 'Website');
    }

    private static function headerLines(string $to, string $subject, ?string $replyTo, array $contentHeaders): array
    {
        $clean = static fn (string $v): string => str_replace(["\r", "\n"], '', $v);
        $lines = [
            'From: ' . $clean(mb_encode_mimeheader(self::fromName(), 'UTF-8')) . ' <' . $clean(self::fromAddress()) . '>',
            'To: <' . $clean($to) . '>',
            'Subject: ' . $clean(mb_encode_mimeheader($subject, 'UTF-8')),
            'Date: ' . date('r'),
            'MIME-Version: 1.0',
        ];
        foreach ($contentHeaders as $h) {
            $lines[] = $h;
        }
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $lines[] = 'Reply-To: <' . $clean($replyTo) . '>';
        }
        return $lines;
    }

    /**
     * Baut Body + passende Content-Header. Ohne $html reiner Text; mit $html
     * multipart/alternative (Text + HTML). Mit $attachments zusätzlich in
     * multipart/mixed verpackt (jeweils base64-kodiert).
     * @return array{0: string[], 1: string}
     */
    private static function buildContent(string $body, ?string $html, ?array $attachments = null): array
    {
        // Innerer Teil: reiner Text ODER Text+HTML (alternative).
        $alt = 'bwkalt_' . substr(md5($body . (string) $html), 0, 20);
        if ($html === null) {
            $innerHeader = "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
            $innerBody = $body;
        } else {
            $innerHeader = 'Content-Type: multipart/alternative; boundary="' . $alt . '"' . "\r\n";
            $innerBody = "--$alt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
                . $body . "\r\n\r\n"
                . "--$alt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
                . $html . "\r\n\r\n--$alt--\r\n";
        }

        $atts = array_values(array_filter($attachments ?? [], static fn ($a) => is_array($a) && ($a['data'] ?? '') !== ''));
        if ($atts === []) {
            if ($html === null) {
                return [['Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: 8bit'], $body];
            }
            return [['Content-Type: multipart/alternative; boundary="' . $alt . '"'], $innerBody];
        }

        $mix = 'bwkmix_' . substr(md5($body . (string) $html . ($atts[0]['name'] ?? '')), 0, 20);
        $mime = "--$mix\r\n" . $innerHeader . "\r\n" . $innerBody . "\r\n";
        foreach ($atts as $a) {
            $name = preg_replace('/[\r\n"]+/', '', (string) ($a['name'] ?? 'anhang.dat'));
            $type = preg_replace('/[\r\n]+/', '', (string) ($a['mime'] ?? 'application/octet-stream'));
            $mime .= "--$mix\r\n"
                . 'Content-Type: ' . $type . '; name="' . $name . '"' . "\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . 'Content-Disposition: attachment; filename="' . $name . '"' . "\r\n\r\n"
                . chunk_split(base64_encode((string) $a['data'])) . "\r\n";
        }
        $mime .= "--$mix--\r\n";
        return [['Content-Type: multipart/mixed; boundary="' . $mix . '"'], $mime];
    }

    private static function sendPhpMail(string $to, string $subject, string $body, ?string $replyTo, ?string $html, ?array $attachments = null): ?string
    {
        [$contentHeaders, $mimeBody] = self::buildContent($body, $html, $attachments);
        // mail() setzt To/Subject selbst – aus den Headern herausfiltern.
        $headers = array_filter(
            self::headerLines($to, $subject, $replyTo, $contentHeaders),
            static fn (string $line): bool => !str_starts_with($line, 'To:') && !str_starts_with($line, 'Subject:')
        );
        $ok = @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $mimeBody, implode("\r\n", $headers));
        return $ok ? null : 'Der Server konnte die E-Mail nicht übergeben (PHP mail() schlug fehl). Prüfe, ob dein Hosting den Mailversand erlaubt, oder richte SMTP ein.';
    }

    /* ---------- SMTP-Client ---------- */

    private static function sendSmtp(string $to, string $subject, string $body, ?string $replyTo, ?string $html, ?array $attachments = null): ?string
    {
        $host = trim(Setting::get('smtp_host', ''));
        $port = (int) Setting::get('smtp_port', '587');
        $encryption = Setting::get('smtp_encryption', 'tls'); // none | ssl | tls (STARTTLS)
        $user = Setting::get('smtp_user', '');
        $pass = Setting::get('smtp_pass', '');

        if ($host === '' || $port <= 0) {
            return 'SMTP ist aktiviert, aber Host/Port fehlen in den Einstellungen.';
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create(['ssl' => ['SNI_enabled' => true]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if ($fp === false) {
            return "Verbindung zu $host:$port fehlgeschlagen: $errstr";
        }
        stream_set_timeout($fp, 15);

        $lastReply = '';
        $read = static function () use ($fp, &$lastReply): int {
            $lastReply = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $lastReply .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') {
                    break;
                }
            }
            return (int) substr($lastReply, 0, 3);
        };
        $cmd = static function (string $command, array $expect) use ($fp, $read, &$lastReply): ?string {
            fwrite($fp, $command . "\r\n");
            $code = $read();
            if (!in_array($code, $expect, true)) {
                return 'SMTP-Fehler nach "' . preg_replace('/^(AUTH|.{0,12}).*/s', '$1…', $command) . '": ' . trim($lastReply);
            }
            return null;
        };

        try {
            if ($read() !== 220) {
                return 'Unerwartete SMTP-Begrüßung: ' . trim($lastReply);
            }
            $hello = 'EHLO ' . (explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0] ?: 'localhost');
            if (($e = $cmd($hello, [250])) !== null) {
                return $e;
            }

            if ($encryption === 'tls') {
                if (($e = $cmd('STARTTLS', [220])) !== null) {
                    return $e;
                }
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return 'STARTTLS-Verschlüsselung konnte nicht aufgebaut werden.';
                }
                if (($e = $cmd($hello, [250])) !== null) {
                    return $e;
                }
            }

            if ($user !== '') {
                if (($e = $cmd('AUTH LOGIN', [334])) !== null) {
                    return $e;
                }
                if (($e = $cmd(base64_encode($user), [334])) !== null) {
                    return $e;
                }
                if (($e = $cmd(base64_encode($pass), [235])) !== null) {
                    return 'SMTP-Anmeldung fehlgeschlagen: ' . trim($lastReply);
                }
            }

            if (($e = $cmd('MAIL FROM:<' . self::fromAddress() . '>', [250])) !== null) {
                return $e;
            }
            if (($e = $cmd('RCPT TO:<' . str_replace(["\r", "\n"], '', $to) . '>', [250, 251])) !== null) {
                return $e;
            }
            if (($e = $cmd('DATA', [354])) !== null) {
                return $e;
            }

            [$contentHeaders, $mimeBody] = self::buildContent($body, $html, $attachments);
            $data = implode("\r\n", self::headerLines($to, $subject, $replyTo, $contentHeaders)) . "\r\n\r\n";
            // Punkt-Stuffing gemäß RFC 5321.
            $data .= preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $mimeBody));
            $data = str_replace("\n", "\r\n", $data);
            if (($e = $cmd($data . "\r\n.", [250])) !== null) {
                return $e;
            }

            fwrite($fp, "QUIT\r\n");
            return null;
        } finally {
            fclose($fp);
        }
    }
}
