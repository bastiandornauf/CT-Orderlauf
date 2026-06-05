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

    /** @return array{ok: bool, error?: string, detail?: string, log: string} */
    public function testSmtpConnection(): array
    {
        $host = trim((string) $this->settings->get('smtp_host', ''));
        $port = (int) $this->settings->get('smtp_port', '587');
        $user = trim((string) $this->settings->get('smtp_user', ''));
        $pass = trim((string) $this->settings->get('smtp_pass', ''), " \t\r\n\v\f\0");

        $log = [];
        $log[] = "Host: {$host}:{$port}";
        $log[] = "User: {$user}";
        $log[] = "Pass-Laenge: " . strlen($pass) . " Zeichen";
        $log[] = "EHLO-Name: " . (gethostname() ?: 'localhost');
        $log[] = str_repeat('-', 40);

        if ($host === '') {
            return ['ok' => false, 'error' => 'Kein SMTP-Server konfiguriert.', 'log' => implode("\n", $log)];
        }
        if ($user === '' || $pass === '') {
            return ['ok' => false, 'error' => 'SMTP-Benutzer oder Passwort fehlt.', 'log' => implode("\n", $log)];
        }

        $prefix = $port === 465 ? 'ssl://' : '';
        $log[] = "Verbinde {$prefix}{$host}:{$port} ...";
        $conn = @stream_socket_client(
            "{$prefix}{$host}:{$port}", $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
        );
        if (!$conn) {
            $log[] = "FEHLER: {$errstr}";
            return ['ok' => false, 'error' => "Verbindung fehlgeschlagen: {$errstr}", 'log' => implode("\n", $log)];
        }
        stream_set_timeout($conn, 15);
        $log[] = 'Verbunden.';

        try {
            $greeting = $this->smtpRead($conn);
            $log[] = "S: {$greeting}";

            $ehloHost = gethostname() ?: 'localhost';
            $log[] = "C: EHLO {$ehloHost}";
            $ehloResp = $this->smtpCmd($conn, "EHLO {$ehloHost}", 250);
            foreach (explode("\n", $ehloResp) as $el) {
                $log[] = 'S: ' . trim($el);
            }

            if ($port !== 465) {
                $log[] = 'C: STARTTLS';
                $stResp = $this->smtpCmd($conn, 'STARTTLS', 220);
                $log[] = "S: {$stResp}";
                $tlsMethod = defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')
                    ? (int) constant('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')
                    : STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (!@stream_socket_enable_crypto($conn, true, $tlsMethod)) {
                    $log[] = 'FEHLER: TLS-Handshake fehlgeschlagen';
                    return ['ok' => false, 'error' => 'STARTTLS fehlgeschlagen.', 'log' => implode("\n", $log)];
                }
                $log[] = 'TLS aktiv.';
                $log[] = "C: EHLO {$ehloHost}";
                $ehlo2 = $this->smtpCmd($conn, "EHLO {$ehloHost}", 250);
                foreach (explode("\n", $ehlo2) as $el) {
                    $log[] = 'S: ' . trim($el);
                }
            }

            $log[] = 'C: AUTH PLAIN <base64>';
            fwrite($conn, 'AUTH PLAIN ' . base64_encode("\0{$user}\0{$pass}") . "\r\n");
            $r1 = $this->smtpRead($conn);
            $log[] = "S: {$r1}";
            $c1 = (int) substr($r1, 0, 3);

            if ($c1 === 235) {
                $log[] = 'AUTH PLAIN OK.';
            } else {
                $log[] = "AUTH PLAIN fehlgeschlagen ({$c1}).";
                $log[] = 'C: RSET';
                fwrite($conn, "RSET\r\n");
                $log[] = 'S: ' . $this->smtpRead($conn);

                $log[] = 'C: AUTH LOGIN';
                fwrite($conn, "AUTH LOGIN\r\n");
                $al1 = $this->smtpRead($conn);
                $log[] = "S: {$al1}";

                $log[] = 'C: <base64 user>';
                fwrite($conn, base64_encode($user) . "\r\n");
                $al2 = $this->smtpRead($conn);
                $log[] = "S: {$al2}";

                $log[] = 'C: <base64 pass>';
                fwrite($conn, base64_encode($pass) . "\r\n");
                $al3 = $this->smtpRead($conn);
                $log[] = "S: {$al3}";

                if ((int) substr($al3, 0, 3) === 235) {
                    $log[] = 'AUTH LOGIN OK.';
                } else {
                    $log[] = 'AUTH LOGIN fehlgeschlagen.';
                    $log[] = str_repeat('-', 40);
                    $log[] = 'ERGEBNIS: Anmeldung gescheitert.';
                    return ['ok' => false, 'error' => "Anmeldung fehlgeschlagen: {$al3}", 'log' => implode("\n", $log)];
                }
            }

            $log[] = 'C: QUIT';
            fwrite($conn, "QUIT\r\n");
            $log[] = 'S: ' . $this->smtpRead($conn);
        } catch (\RuntimeException $e) {
            $log[] = "FEHLER: {$e->getMessage()}";
            return ['ok' => false, 'error' => $e->getMessage(), 'log' => implode("\n", $log)];
        } finally {
            @fclose($conn);
        }

        $log[] = str_repeat('-', 40);
        $log[] = 'ERGEBNIS: Anmeldung erfolgreich. Keine Mail versendet.';
        return ['ok' => true, 'detail' => "Anmeldung bei {$host}:{$port} als {$user} erfolgreich.", 'log' => implode("\n", $log)];
    }

    /**
     * @param array{data: string, filename: string}|null $attachment  PDF binary + filename
     * @return array{ok: bool, error?: string}
     */
    public function send(string $to, string $subject, string $body, string $cc = '', ?array $attachment = null): array
    {
        if ($to === '') {
            return ['ok' => false, 'error' => 'Kein Empfaenger angegeben.'];
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
     * Liefert einzelne E-Mail-Adressen aus einem freien CC-String (Komma, Semikolon, „Name <mail>“).
     *
     * @return list<string>
     */
    private function parseRecipientAddresses(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[,;]/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $e = trim((string) $p);
            if ($e === '') {
                continue;
            }
            if (preg_match('/<([^>]+)>/', $e, $m)) {
                $e = trim($m[1]);
            }
            if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $out[] = $e;
            }
        }

        return array_values(array_unique($out, SORT_STRING));
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

        $toList = $this->parseRecipientAddresses($to);
        $toNorm = $toList[0] ?? trim($to);
        $ccList = $this->parseRecipientAddresses($cc);
        $ccList = array_values(array_filter(
            $ccList,
            static fn (string $a): bool => strcasecmp($a, $toNorm) !== 0
        ));
        $ccHeader = $ccList !== [] ? implode(', ', $ccList) : '';

        $headers = [
            "From: {$from}",
            "Reply-To: {$fromEmail}",
            "To: {$toNorm}",
            'MIME-Version: 1.0',
        ];
        if ($ccHeader !== '') {
            $headers[] = "Cc: {$ccHeader}";
        }

        // Ohne SMTP liest viele MTA nur den ersten Empfaenger aus mail() — CC muss in die Empfaengerliste.
        $envelopeTo = $toNorm;
        if ($ccHeader !== '') {
            $envelopeTo .= ', ' . implode(', ', $ccList);
        }

        if ($attachment !== null) {
            $mime = $this->buildMimeBody($body, $attachment);
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$mime['boundary']}\"";
            $ok = @mail($envelopeTo, $encodedSubject, $mime['content'], implode("\r\n", $headers));
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $ok = @mail($envelopeTo, $encodedSubject, $body, implode("\r\n", $headers));
        }

        return $ok
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'PHP mail() fehlgeschlagen.'];
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
        $pass = trim((string) $this->settings->get('smtp_pass', ''), " \t\r\n\v\f\0");

        if ($host === '' || $user === '' || $pass === '') {
            return ['ok' => false, 'error' => 'SMTP nicht vollstaendig konfiguriert (Host/User/Passwort).'];
        }

        $smtpFromSetting = trim((string) $this->settings->get('smtp_from_email', ''));
        $fromViaSmtpUser = SmtpHost::requiresSenderEqualsSmtpUser($host)
            && filter_var($user, FILTER_VALIDATE_EMAIL);
        if (!$fromViaSmtpUser && $smtpFromSetting === '') {
            return [
                'ok' => false,
                'error' => 'Absender-Adresse fehlt: Unter Einstellungen → E-Mail-Versand → „SMTP-Server konfigurieren“ das Feld „Absender-Adresse“ ausfüllen (z. B. dieselbe Adresse wie beim SMTP-Benutzer). Ohne diese Adresse ist der Versand über SMTP nicht möglich.',
            ];
        }

        $fromEmail = $this->resolveFromEmail();
        $fromName = $this->resolveFromName();
        if (SmtpHost::requiresSenderEqualsSmtpUser($host) && $user !== '') {
            $fromEmail = $user;
        }

        $prefix = $port === 465 ? 'ssl://' : '';
        $conn = @stream_socket_client(
            "{$prefix}{$host}:{$port}", $errno, $errstr, 15,
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
                if (!@stream_socket_enable_crypto($conn, true, $tlsMethod)) {
                    return ['ok' => false, 'error' => 'STARTTLS fehlgeschlagen (TLS-Handshake).'];
                }
                $this->smtpCmd($conn, "EHLO " . gethostname(), 250);
            }

            $this->smtpAuthenticate($conn, $user, $pass);

            $toList = $this->parseRecipientAddresses($to);
            $toNorm = $toList[0] ?? trim($to);
            $ccAddresses = $this->parseRecipientAddresses($cc);
            $ccAddresses = array_values(array_filter(
                $ccAddresses,
                static fn (string $a): bool => strcasecmp($a, $toNorm) !== 0
            ));
            $ccHeader = $ccAddresses !== [] ? implode(', ', $ccAddresses) : '';

            $this->smtpCmd($conn, "MAIL FROM:<{$fromEmail}>", 250);
            $this->smtpCmd($conn, "RCPT TO:<{$toNorm}>", 250);
            foreach ($ccAddresses as $ccAddr) {
                $this->smtpCmd($conn, "RCPT TO:<{$ccAddr}>", 250);
            }

            $this->smtpCmd($conn, "DATA", 354);

            $fromHeader = $fromName !== ''
                ? "=?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>"
                : $fromEmail;

            $msg = "From: {$fromHeader}\r\n";
            $msg .= "To: {$toNorm}\r\n";
            if ($ccHeader !== '') {
                $msg .= "Cc: {$ccHeader}\r\n";
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
            $emsg = $e->getMessage();
            if (str_contains($emsg, '535') || str_contains($emsg, 'authentication failed')) {
                $emsg .= ' -- Anmeldung abgelehnt.';
            }
            return ['ok' => false, 'error' => $emsg];
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

    /** AUTH PLAIN dann AUTH LOGIN */
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
            $parsed = $this->parseRecipientAddresses($smtp);
            if ($parsed !== []) {
                return $parsed[0];
            }
            if (filter_var($smtp, FILTER_VALIDATE_EMAIL)) {
                return $smtp;
            }
        }

        // Kein Fallback mehr auf order_cc_email: Wenn From und Cc dieselbe Mailbox sind,
        // unterdrücken u. a. Microsoft 365 / Exchange oft die CC-Kopie an denselben Empfänger.
        return 'noreply@' . $this->defaultMailHostname();
    }

    /** Hostname für noreply@… (nur ASCII, ohne Port). */
    private function defaultMailHostname(): string
    {
        $host = trim((string) ($_SERVER['SERVER_NAME'] ?? ''));
        if ($host === '' || strcasecmp($host, 'localhost') === 0 || filter_var($host, FILTER_VALIDATE_IP)) {
            $fromHttp = (string) ($_SERVER['HTTP_HOST'] ?? '');
            $host = preg_replace('/:\d+$/', '', trim($fromHttp)) ?: 'localhost';
        }
        $host = strtolower($host);
        $host = preg_replace('/[^a-z0-9.-]+/', '', $host) ?: 'localhost';

        return $host;
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
