<?php

declare(strict_types=1);

namespace App\Services\Inbox;

/**
 * Minimaler IMAP-Client (nur lesen) über TLS-Socket – analog zum eigenen SMTP-Code.
 * Holt Mails ausschließlich mit BODY.PEEK: nichts wird als gelesen markiert, verschoben oder gelöscht.
 */
final class ImapClient
{
    /** @var resource|null */
    private $conn = null;
    private int $tag = 0;

    /** @param bool $plain nur für lokale Test-Mailserver: ohne TLS */
    public function connect(string $host, int $port, string $user, string $pass, int $timeout = 20, bool $plain = false): void
    {
        $prefix = $port === 143 || $plain ? 'tcp://' : 'ssl://';
        $conn = @stream_socket_client(
            "{$prefix}{$host}:{$port}", $errno, $errstr, $timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
        );
        if (!$conn) {
            throw new \RuntimeException("IMAP-Verbindung zu {$host}:{$port} fehlgeschlagen: {$errstr}");
        }
        stream_set_timeout($conn, $timeout);
        $this->conn = $conn;
        $greeting = $this->readLine();
        if (!str_starts_with($greeting, '* OK')) {
            throw new \RuntimeException('IMAP-Server antwortet unerwartet: ' . trim($greeting));
        }
        if ($port === 143 && !$plain) {
            $this->command('STARTTLS');
            if (!@stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('IMAP: STARTTLS fehlgeschlagen.');
            }
        }
        try {
            $this->command('LOGIN ' . $this->quote($user) . ' ' . $this->quote($pass));
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('IMAP-Anmeldung fehlgeschlagen – Benutzer/Passwort wie beim SMTP-Versand prüfen.', 0, $e);
        }
    }

    /** Postfach nur lesend öffnen. @return int UIDVALIDITY */
    public function examine(string $mailbox = 'INBOX'): int
    {
        foreach ($this->command('EXAMINE ' . $this->quote($mailbox)) as $r) {
            if (preg_match('/\[UIDVALIDITY (\d+)\]/i', $r['line'], $m)) {
                return (int) $m[1];
            }
        }

        return 0;
    }

    /** @return list<int> */
    public function searchUids(string $criteria): array
    {
        $uids = [];
        foreach ($this->command('UID SEARCH ' . $criteria) as $r) {
            if (preg_match('/^\* SEARCH\b(.*)$/i', $r['line'], $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) ?: [] as $n) {
                    if ($n !== '' && ctype_digit($n)) {
                        $uids[] = (int) $n;
                    }
                }
            }
        }
        sort($uids);

        return $uids;
    }

    /** @return array<int, int> UID → Größe in Bytes */
    public function sizes(array $uids): array
    {
        if ($uids === []) {
            return [];
        }
        $out = [];
        foreach ($this->command('UID FETCH ' . implode(',', $uids) . ' (UID RFC822.SIZE)') as $r) {
            if (preg_match('/UID (\d+)/i', $r['line'], $u) && preg_match('/RFC822\.SIZE (\d+)/i', $r['line'], $s)) {
                $out[(int) $u[1]] = (int) $s[1];
            }
        }

        return $out;
    }

    /** @return array{raw:string, internaldate:string}|null */
    public function fetch(int $uid): ?array
    {
        foreach ($this->command("UID FETCH {$uid} (UID INTERNALDATE BODY.PEEK[])") as $r) {
            if (!preg_match('/\bFETCH\b/i', $r['line']) || $r['literals'] === []) {
                continue;
            }
            preg_match('/INTERNALDATE "([^"]+)"/i', $r['line'], $d);

            return ['raw' => $r['literals'][0], 'internaldate' => $d[1] ?? ''];
        }

        return null;
    }

    public function logout(): void
    {
        if ($this->conn === null) {
            return;
        }
        try {
            $this->command('LOGOUT');
        } catch (\Throwable) {
            // egal – Verbindung wird ohnehin geschlossen
        }
        fclose($this->conn);
        $this->conn = null;
    }

    public function __destruct()
    {
        if ($this->conn !== null) {
            @fclose($this->conn);
        }
    }

    /**
     * Befehl senden und alle ungetaggten Antworten bis zur Abschlusszeile lesen.
     *
     * @return list<array{line:string, literals:list<string>}>
     */
    private function command(string $cmd): array
    {
        $tag = 'A' . (++$this->tag);
        $this->write("{$tag} {$cmd}\r\n");
        $responses = [];
        while (true) {
            $line = $this->readLine();
            $literals = [];
            // Literal {n}: genau n Bytes folgen, danach geht die Zeile weiter
            while (preg_match('/\{(\d+)\}\r\n$/', $line, $m)) {
                $literals[] = $this->readBytes((int) $m[1]);
                $line = substr($line, 0, -strlen($m[0])) . '{…}' . $this->readLine();
            }
            if (str_starts_with($line, $tag . ' ')) {
                $status = strtoupper(substr($line, strlen($tag) + 1, 2));
                if ($status !== 'OK') {
                    $shown = str_starts_with($cmd, 'LOGIN ') ? 'LOGIN' : $cmd;
                    throw new \RuntimeException("IMAP-Befehl {$shown} fehlgeschlagen: " . trim(substr($line, strlen($tag) + 1)));
                }

                return $responses;
            }
            $responses[] = ['line' => rtrim($line), 'literals' => $literals];
        }
    }

    private function quote(string $s): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
    }

    private function write(string $data): void
    {
        if ($this->conn === null || @fwrite($this->conn, $data) === false) {
            throw new \RuntimeException('IMAP: Senden fehlgeschlagen.');
        }
    }

    private function readLine(): string
    {
        $line = $this->conn !== null ? fgets($this->conn) : false;
        if ($line === false) {
            $meta = $this->conn !== null ? stream_get_meta_data($this->conn) : [];
            throw new \RuntimeException(!empty($meta['timed_out']) ? 'IMAP: Zeitüberschreitung.' : 'IMAP: Verbindung abgebrochen.');
        }

        return $line;
    }

    private function readBytes(int $n): string
    {
        $buf = '';
        while (strlen($buf) < $n) {
            $chunk = fread($this->conn, min(65536, $n - strlen($buf)));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->conn);
                if (!empty($meta['timed_out']) || feof($this->conn)) {
                    throw new \RuntimeException('IMAP: Mail konnte nicht vollständig gelesen werden.');
                }
                continue;
            }
            $buf .= $chunk;
        }

        return $buf;
    }
}
