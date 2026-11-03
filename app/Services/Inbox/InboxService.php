<?php

declare(strict_types=1);

namespace App\Services\Inbox;

use App\Helpers\Database;
use App\Helpers\SmtpHost;
use App\Repositories\InboxRepository;
use App\Repositories\SettingsRepository;
use PDO;

/**
 * Posteingang: Mails aus dem Versand-Postfach abholen, einordnen, Anhänge ablegen.
 * Abholen ist idempotent (UID + Message-ID) und gedrosselt; die Mails bleiben unverändert im Postfach.
 */
final class InboxService
{
    public const THROTTLE_SECONDS = 600;
    private const FIRST_RUN_DAYS = 30;
    private const MAX_PER_RUN = 30;
    private const MAX_MAIL_BYTES = 25_000_000;
    private const MAX_BODY_CHARS = 20_000;
    private const TIMEZONE = 'Europe/Berlin';

    public function __construct(
        private SettingsRepository $settings = new SettingsRepository(),
        private InboxRepository $repo = new InboxRepository(),
    ) {
    }

    public static function storageDir(): string
    {
        return APP_ROOT . '/storage/inbox';
    }

    /** @return list<string> */
    public function ownAddresses(): array
    {
        return InboxClassifier::splitAddresses((string) $this->settings->get('inbox_own_addresses', ''));
    }

    public function imapHost(): string
    {
        $host = trim((string) $this->settings->get('imap_host', ''));
        if ($host !== '') {
            return $host;
        }
        $smtp = strtolower(trim((string) $this->settings->get('smtp_host', '')));
        if (SmtpHost::requiresSenderEqualsSmtpUser($smtp) || str_contains($smtp, 'ionos')) {
            return 'imap.ionos.de';
        }

        return $smtp !== '' ? (string) preg_replace('/^(smtp|mail)\./', 'imap.', $smtp) : '';
    }

    public function isConfigured(): bool
    {
        return $this->imapHost() !== ''
            && trim((string) $this->settings->get('smtp_user', '')) !== ''
            && trim((string) $this->settings->get('smtp_pass', '')) !== '';
    }

    public function lastFetchAt(): ?int
    {
        $v = (int) $this->settings->get('inbox_last_fetch', '0');

        return $v > 0 ? $v : null;
    }

    /**
     * @return array{ok:bool, fetched:int, skipped:bool, error?:string, more?:bool}
     */
    public function fetch(bool $force = false): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'fetched' => 0, 'skipped' => true, 'error' => 'Postfach nicht eingerichtet (SMTP-Zugang fehlt).'];
        }
        $last = $this->lastFetchAt();
        if (!$force && $last !== null && time() - $last < self::THROTTLE_SECONDS) {
            return ['ok' => true, 'fetched' => 0, 'skipped' => true];
        }
        $pdo = Database::pdo();
        // Zwei Admins gleichzeitig auf der Startseite → nur einer holt ab
        if ((int) $pdo->query("SELECT GET_LOCK('orderlauf_inbox_fetch', 0)")->fetchColumn() !== 1) {
            return ['ok' => true, 'fetched' => 0, 'skipped' => true];
        }
        $this->settings->set('inbox_last_fetch', (string) time());
        @set_time_limit(120);

        $imap = new ImapClient();
        try {
            $host = $this->imapHost();
            $port = (int) ($this->settings->get('imap_port', '993') ?: 993);
            // Ohne TLS nur gegen einen lokalen Test-Mailserver (Docker), nie gegen echte Postfächer
            $plain = $port !== 993 && $port !== 143 && in_array($host, ['localhost', '127.0.0.1', 'host.docker.internal'], true);
            $imap->connect(
                $host,
                $port,
                trim((string) $this->settings->get('smtp_user', '')),
                trim((string) $this->settings->get('smtp_pass', ''), " \t\r\n\v\f\0"),
                20,
                $plain
            );
            $uidValidity = $imap->examine('INBOX');
            $storedValidity = (int) $this->settings->get('inbox_uidvalidity', '0');
            $lastUid = $storedValidity === $uidValidity ? (int) $this->settings->get('inbox_last_uid', '0') : 0;

            $uids = $lastUid > 0
                ? $imap->searchUids('UID ' . ($lastUid + 1) . ':*')
                : $imap->searchUids('SINCE ' . date('d-M-Y', strtotime('-' . self::FIRST_RUN_DAYS . ' days')));
            $uids = array_values(array_filter($uids, static fn (int $u): bool => $u > $lastUid));
            $more = count($uids) > self::MAX_PER_RUN;
            $uids = array_slice($uids, 0, self::MAX_PER_RUN);

            $classifier = $this->classifier();
            $sizes = $imap->sizes($uids);
            $fetched = 0;
            foreach ($uids as $uid) {
                if (!$this->repo->existsByUid($uidValidity, $uid)) {
                    $fetched += $this->importOne($imap, $classifier, $uidValidity, $uid, $sizes[$uid] ?? 0) ? 1 : 0;
                }
                $lastUid = max($lastUid, $uid);
                $this->settings->set('inbox_uidvalidity', (string) $uidValidity);
                $this->settings->set('inbox_last_uid', (string) $lastUid);
            }
            $imap->logout();
            $this->settings->set('inbox_last_error', null);

            return ['ok' => true, 'fetched' => $fetched, 'skipped' => false, 'more' => $more];
        } catch (\Throwable $e) {
            $imap->logout();
            $msg = $e->getMessage();
            $this->settings->set('inbox_last_error', $msg);

            return ['ok' => false, 'fetched' => 0, 'skipped' => false, 'error' => $msg];
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('orderlauf_inbox_fetch')");
        }
    }

    /**
     * Vorhandene Nachrichten nach den aktuellen Regeln neu einordnen (z. B. nach Änderung der eigenen Adressen).
     * Neue Nachträge werden abgehakt; sonst bleibt der Status, wie er ist.
     *
     * @return int Anzahl geänderter Nachrichten
     */
    public function reclassify(): int
    {
        $classifier = $this->classifier();
        $changed = 0;
        foreach ($this->repo->list('alle', 100000) as $m) {
            $c = $classifier->classify((string) $m['from_email'], (string) $m['subject']);
            if ($c['kind'] === $m['kind'] && $c['supplier_id'] === $m['supplier_id']) {
                continue;
            }
            $this->repo->setClassification($m['id'], $c['kind'], $c['supplier_id']);
            $changed++;
        }

        return $changed;
    }

    public function lastError(): string
    {
        return (string) $this->settings->get('inbox_last_error', '');
    }

    private function classifier(): InboxClassifier
    {
        $emails = Database::pdo()->query("SELECT id, email FROM suppliers WHERE email IS NOT NULL AND email <> ''")
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        return new InboxClassifier($this->ownAddresses(), $emails ?: []);
    }

    private function importOne(ImapClient $imap, InboxClassifier $classifier, int $uidValidity, int $uid, int $size): bool
    {
        if ($size > self::MAX_MAIL_BYTES) {
            $this->repo->insert([
                'imap_uid' => $uid, 'imap_uidvalidity' => $uidValidity, 'message_id' => '',
                'received_at' => self::now(), 'from_email' => '', 'from_name' => '',
                'subject' => '(Mail zu groß – ' . round($size / 1_000_000) . ' MB, bitte im Postfach ansehen)',
                'body_text' => null, 'kind' => 'sonstiges', 'supplier_id' => null, 'status' => 'neu',
            ]);

            return true;
        }
        $msg = $imap->fetch($uid);
        if ($msg === null) {
            return false;
        }
        $mail = MimeParser::parse($msg['raw']);
        if ($this->repo->existsByMessageId($mail['message_id'])) {
            return false;   // nach UIDVALIDITY-Wechsel schon bekannt
        }
        $class = $classifier->classify($mail['from_email'], $mail['subject']);
        $text = $mail['text'];
        if (mb_strlen($text) > self::MAX_BODY_CHARS) {
            $text = mb_substr($text, 0, self::MAX_BODY_CHARS) . "\n…";
        }
        $id = $this->repo->insert([
            'imap_uid' => $uid,
            'imap_uidvalidity' => $uidValidity,
            'message_id' => $mail['message_id'],
            'received_at' => self::receivedAt($msg['internaldate'], $mail['date']),
            'from_email' => $mail['from_email'],
            'from_name' => $mail['from_name'],
            'subject' => $mail['subject'] !== '' ? $mail['subject'] : '(ohne Betreff)',
            'body_text' => $text,
            'kind' => $class['kind'],
            'supplier_id' => $class['supplier_id'],
            'status' => $class['kind'] === 'nachtrag' ? 'erledigt' : 'neu',
        ]);
        if ($mail['attachments'] !== [] && $class['kind'] !== 'nachtrag') {
            $this->repo->setAttachments($id, $this->storeAttachments($id, $mail['attachments']));
        }

        return true;
    }

    /**
     * @param list<array{name:string, mime:string, content:string}> $attachments
     * @return list<array{name:string, file:string, mime:string, size:int}>
     */
    private function storeAttachments(int $id, array $attachments): array
    {
        $dir = self::storageDir() . '/' . $id;
        self::ensureDir($dir);
        $out = [];
        foreach ($attachments as $i => $a) {
            $file = ($i + 1) . '_' . self::safeName($a['name']);
            if (@file_put_contents($dir . '/' . $file, $a['content']) === false) {
                throw new \RuntimeException('Anhang konnte nicht gespeichert werden – ist storage/inbox/ beschreibbar?');
            }
            $out[] = ['name' => $a['name'], 'file' => $file, 'mime' => $a['mime'], 'size' => strlen($a['content'])];
        }

        return $out;
    }

    public static function attachmentPath(int $id, string $file): ?string
    {
        if ($file === '' || $file !== basename($file) || str_starts_with($file, '.')) {
            return null;
        }
        $path = self::storageDir() . '/' . $id . '/' . $file;

        return is_file($path) ? $path : null;
    }

    private static function ensureDir(string $dir): void
    {
        $root = dirname(self::storageDir());
        if (!is_dir($root) && !@mkdir($root, 0750, true) && !is_dir($root)) {
            throw new \RuntimeException('Ordner storage/ konnte nicht angelegt werden – Schreibrechte im Projektordner prüfen.');
        }
        // Falls der Webserver doch einmal auf das Projektroot zeigt: nichts ausliefern
        if (!is_file($root . '/.htaccess')) {
            @file_put_contents($root . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Ordner für Anhänge konnte nicht angelegt werden – ist storage/inbox/ beschreibbar?');
        }
    }

    public static function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', $name);
        $name = trim($name, ' ._');
        if ($name === '') {
            $name = 'anhang';
        }

        return mb_substr($name, -120);
    }

    private static function receivedAt(string $internalDate, string $headerDate): string
    {
        foreach ([$internalDate, $headerDate] as $d) {
            if ($d === '') {
                continue;
            }
            try {
                return (new \DateTimeImmutable($d))
                    ->setTimezone(new \DateTimeZone(self::TIMEZONE))
                    ->format('Y-m-d H:i:s');
            } catch (\Exception) {
                // nächster Versuch
            }
        }

        return self::now();
    }

    /** Eingangszeiten immer in deutscher Zeit – die App setzt keine Zeitzone, der Server läuft evtl. auf UTC */
    public static function now(string $format = 'Y-m-d H:i:s', ?int $ts = null): string
    {
        return (new \DateTimeImmutable('@' . ($ts ?? time())))->setTimezone(new \DateTimeZone(self::TIMEZONE))->format($format);
    }
}
