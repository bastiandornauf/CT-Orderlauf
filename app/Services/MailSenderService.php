<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\SmtpHost;
use App\Repositories\SettingsRepository;

final class MailSenderService
{
    private SettingsRepository $settings;

    public function __construct(?SettingsRepository $settings = null)
    {
        $this->settings = $settings ?? new SettingsRepository();
    }

    public function isDirectSendEnabled(): bool
    {
        return $this->settings->get('send_email_direct', '0') === '1';
    }

    /**
     * Minimaler Test: TCP + TLS + SMTP-Auth, danach QUIT — kein Versand, keine MAIL FROM/RCPT/DATA.
     *
     * @return array{ok: bool, error?: string, detail?: string}
     */
    public function testSmtpConnection(): array
    {
        $host = trim((string) $this->settings->get('smtp_host', ''));
        $port = (int) $this->settings->get('smtp_port', '587');
        $user = trim((string) $this->settings->get('smtp_user', ''));
        $pass = trim((string) $this->settings->get('smtp_pass', ''), " \t\r\n\v\f\0");

        if ($host === '') {
            return ['ok' => false, 'error' => 'Kein SMTP-Server konfiguriert.'];
        }
        if ($user === '' || $pass === '') {
            return ['ok' => false, 'error' => 'SMTP-Benutzer oder Passwort fehlt (Passwort nach Änderung speichern).'];
        }

        $prefix = $port === 465 ? 'ssl://' : '';
        $errno = 0;
        $errstr = '';
        $conn = @stream_socket_client(
            "{$prefix}{$host}:{$port}",
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
        );
        if (!$conn) {
            return ['ok' => false, 'error' => "Verbindung zu {$host}:{$port} fehlgeschlagen: {$errstr}"];
        }
        stream_set_timeout($conn, 15);

        try {
            $this->smtpRead($conn);
            $this->smtpCmd($conn, 'EHLO ' . gethostname(), 250);

            if ($port !== 465) {
                $this->smtpCmd($conn, 'STARTTLS', 220);
                $tlsMethod = defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')
                    ? (int) constant('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')
                    : STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (!@stream_socket_enable_crypto($conn, true, $tlsMethod)) {
                    return ['ok' => false, 'error' => 'STARTTLS fehlgeschlagen (TLS-Handshake).'];
                }
                $this->smtpCmd($conn, 'EHLO ' . gethostname(), 250);
            }

            $this->smtpAuthenticate($conn, $user, $pass);
            $this->smtpCmd($conn, 'QUIT', 221);
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '535') || str_contains($msg, 'authentication failed')) {
                $msg .= ' — Anmeldung abgelehnt. Typisch: Benutzername = vollständige E-Mail des Postfachs (nicht Kundennummer/FTP); Passwort = genau das Postfach-Passwort. Im Webmail testen; Sonderzeichen im Passwort ggf. im Postfach neu setzen.';
            }

            return ['ok' => false, 'error' => $msg];
        } finally {
            @fclose($conn);
        }

        $detail = "Server {$host}:{$port}, Benutzer „{$user}“ – Anmeldung ok, keine Mail versendet.";
        if (SmtpHost::requiresSenderEqualsSmtpUser($host)) {
            $detail .= ' Absender-Adresse und SMTP-Benutzer sollten übereinstimmen (Anbieter-Vorgabe).';
        }

        return ['ok' => true, 'detail' => $detail];
    }

    /**
     * @param array{data: string, filename: string}|null $attachment  PDF binary + filename
     * @return array{ok: bool, error?: string}
     */
    public function send(string $to, string $subject, string $body, string $cc = '', ?array $attachment = null): array
    {
        if ($to === '') {
            return ['ok' => false, 'error' => 'Kein Empfänger angegeben.'];
        }

        $devMode = $this->settings->get('dev_mode', '0') === '1';
        $devEmail = trim((string) $this->settings->get('dev_email', ''));
        if ($devMode && $devEmail !== '') {
            $subject = "[TEST an {$to}] {$subject}";
            $to = $devEmail;
            $cc = '';
        }

        $smtpHost = trim((string) $this->settings->get('smtp_host', ''));
        if ($smtpHost !== '') {
            return $this->sendSmtp($to, $subject, $body, $cc, $attachment);
        }
        return $this->sendPhpMail($to, $subject, $body, $cc, $attachment);
    }

    /**
     * @param array{data: string, filename: string}|null $attachment
     * @return array{ok: bool, error?: string}
     */
    private function sendPhpMail(string $to, string $subject, string $body, string $cc, ?array $attachment): array
    {
        $fromEmail = $this->resolveFromEmail();
        $fromName = $this->resolveFromName();
        $from = $fromName !== '' ? "=?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>" : $fromEmail;
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $headers = [
            "From: {$from}",
            "Reply-To: {$fromEmail}",
            'MIME-Version: 1.0',
        ];
        if ($cc !== '') {
            $headers[] = "Cc: {$cc}";
        }

        if ($attachment !== null) {
            $mime = $this->buildMimeBody($body, $attachment);
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$mime['boundary']}\"";
            $ok = @mail($to, $encodedSubject, $mime['content'], implode("\r\n", $headers));
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $ok = @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
        }

        return $ok
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'PHP mail() fehlgeschlagen – ggf. ist Mailversand auf diesem Server nicht konfiguriert.'];
    }

    /**
     * @param array{data: string, filename: string}|null $attachment
     * @return array{ok: bool, error?: string}
     */
    private function sendSmtp(string $to, string $subject, string $body, string $cc, ?array $attachment): array
    {
        $host = trim((string) $this->settings->get('smtp_host', ''));
        $port = (int) $this->settings->get('smtp_port', '587');
        $user = trim((string) $this->settings->get('smtp_user', ''));
        // Nur äußere Leerzeichen/Kopierreste – kein trim() mitten im Passwort
        $pass = trim((string) $this->settings->get('smtp_pass', ''), " \t\r\n\v\f\0");
        $fromEmail = $this->resolveFromEmail();
        $fromName = $this->resolveFromName();
        if (SmtpHost::requiresSenderEqualsSmtpUser($host) && $user !== '') {
            $fromEmail = $user;
        }

        if ($host === '' || $user === '' || $pass === '') {
            return ['ok' => false, 'error' => 'SMTP nicht vollständig konfiguriert (Host/User/Passwort).'];
        }

        $prefix = $port === 465 ? 'ssl://' : '';
        $errno = 0;
        $errstr = '';
        $conn = @stream_socket_client(
            "{$prefix}{$host}:{$port}",
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
        );
        if (!$conn) {
            return ['ok' => false, 'error' => "SMTP-Verbindung fehlgeschlagen: {$errstr}"];
        }
        stream_set_timeout($conn, 15);

        try {
            $this->smtpRead($conn);
            $this->smtpCmd($conn, "EHLO " . gethostname(), 250);

            if ($port !== 465) {
                $this->smtpCmd($conn, 'STARTTLS', 220);
                $tlsMethod = defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')
                    ? (int) constant('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')
                    : STREAM_CRYPTO_METHOD_TLS_CLIENT;
                $cryptoOk = @stream_socket_enable_crypto($conn, true, $tlsMethod);
                if (!$cryptoOk) {
                    return ['ok' => false, 'error' => 'STARTTLS fehlgeschlagen (TLS-Handshake).'];
                }
                $this->smtpCmd($conn, "EHLO " . gethostname(), 250);
            }

            $this->smtpAuthenticate($conn, $user, $pass);

            $this->smtpCmd($conn, "MAIL FROM:<{$fromEmail}>", 250);
            $this->smtpCmd($conn, "RCPT TO:<{$to}>", 250);
            if ($cc !== '') {
                $this->smtpCmd($conn, "RCPT TO:<{$cc}>", 250);
            }

            $this->smtpCmd($conn, "DATA", 354);

            $fromHeader = $fromName !== ''
                ? "=?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>"
                : $fromEmail;

            $msg = "From: {$fromHeader}\r\n";
            $msg .= "To: {$to}\r\n";
            if ($cc !== '') {
                $msg .= "Cc: {$cc}\r\n";
            }
            $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= "Date: " . date('r') . "\r\n";

            if ($attachment !== null) {
                $mime = $this->buildMimeBody($body, $attachment);
                $msg .= "Content-Type: multipart/mixed; boundary=\"{$mime['boundary']}\"\r\n";
                $msg .= "\r\n";
                $msg .= str_replace("\r\n.", "\r\n..", $mime['content']);
            } else {
                $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $msg .= "Content-Transfer-Encoding: 8bit\r\n";
                $msg .= "\r\n";
                $msg .= str_replace("\r\n.", "\r\n..", $body);
            }
            $msg .= "\r\n.\r\n";

            fwrite($conn, $msg);
            $response = $this->smtpRead($conn);
            if (!str_starts_with($response, '250')) {
                return ['ok' => false, 'error' => "SMTP-Fehler nach DATA: {$response}"];
            }

            $this->smtpCmd($conn, "QUIT", 221);
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '535') || str_contains($msg, 'authentication failed')) {
                $msg .= ' — Anmeldung abgelehnt. Typisch: Benutzername = vollständige E-Mail des Postfachs (nicht Kundennummer/FTP); Passwort = genau das Postfach-Passwort. Im Webmail testen; Sonderzeichen im Passwort ggf. im Postfach neu setzen.';
            }
            return ['ok' => false, 'error' => $msg];
        } finally {
            @fclose($conn);
        }

        return ['ok' => true];
    }

    /**
     * @param array{data: string, filename: string} $attachment
     * @return array{boundary: string, content: string}
     */
    private function buildMimeBody(string $textBody, array $attachment): array
    {
        $boundary = '----=_CT_Orderlauf_' . bin2hex(random_bytes(12));

        $content  = "--{$boundary}\r\n";
        $content .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $content .= "Content-Transfer-Encoding: 8bit\r\n";
        $content .= "\r\n";
        $content .= $textBody . "\r\n\r\n";
        $content .= "--{$boundary}\r\n";
        $encodedName = '=?UTF-8?B?' . base64_encode($attachment['filename']) . '?=';
        $content .= "Content-Type: application/pdf; name=\"{$encodedName}\"\r\n";
        $content .= "Content-Disposition: attachment; filename=\"{$encodedName}\"\r\n";
        $content .= "Content-Transfer-Encoding: base64\r\n";
        $content .= "\r\n";
        $content .= chunk_split(base64_encode($attachment['data']), 76, "\r\n");
        $content .= "--{$boundary}--\r\n";

        return ['boundary' => $boundary, 'content' => $content];
    }

    /** AUTH PLAIN (RFC 4616), ggf. zweite Variante, danach AUTH LOGIN – bessere Kompatibilität als nur LOGIN. */
    private function smtpAuthenticate(mixed $conn, string $user, string $pass): void
    {
        fwrite($conn, 'AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass) . "\r\n");
        $r1 = $this->smtpRead($conn);
        $c1 = (int) substr($r1, 0, 3);
        if ($c1 === 235) {
            return;
        }

        if ($c1 === 535) {
            fwrite($conn, "RSET\r\n");
            $this->smtpRead($conn);
            fwrite($conn, 'AUTH PLAIN ' . base64_encode($user . "\0" . $user . "\0" . $pass) . "\r\n");
            $rAlt = $this->smtpRead($conn);
            if ((int) substr($rAlt, 0, 3) === 235) {
                return;
            }
        }

        fwrite($conn, "RSET\r\n");
        $this->smtpRead($conn);

        $this->smtpCmd($conn, 'AUTH LOGIN', 334);
        $this->smtpCmd($conn, base64_encode($user), 334);
        $this->smtpCmd($conn, base64_encode($pass), 235);
    }

    private function smtpCmd(mixed $conn, string $cmd, int $expectCode): string
    {
        fwrite($conn, $cmd . "\r\n");
        $response = $this->smtpRead($conn);
        $code = (int) substr($response, 0, 3);
        if ($code !== $expectCode) {
            throw new \RuntimeException("SMTP-Fehler: erwartet {$expectCode}, bekommen: {$response}");
        }
        return $response;
    }

    private function smtpRead(mixed $conn): string
    {
        $data = '';
        while ($line = fgets($conn, 512)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($data);
    }

    private function resolveFromEmail(): string
    {
        $smtp = trim((string) $this->settings->get('smtp_from_email', ''));
        if ($smtp !== '') {
            return $smtp;
        }
        $cc = trim((string) $this->settings->get('order_cc_email', ''));
        if ($cc !== '') {
            return $cc;
        }
        return 'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    }

    private function resolveFromName(): string
    {
        $name = trim((string) $this->settings->get('smtp_from_name', ''));
        if ($name !== '') {
            return $name;
        }
        return trim((string) $this->settings->get('company_name', ''));
    }
}
