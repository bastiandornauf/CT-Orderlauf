<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use PDO;

final class InboxRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    public function existsByUid(int $uidValidity, int $uid): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM inbox_messages WHERE imap_uidvalidity = ? AND imap_uid = ?');
        $st->execute([$uidValidity, $uid]);

        return (bool) $st->fetchColumn();
    }

    public function existsByMessageId(string $messageId): bool
    {
        if ($messageId === '') {
            return false;
        }
        $st = $this->pdo->prepare('SELECT 1 FROM inbox_messages WHERE message_id = ? LIMIT 1');
        $st->execute([$messageId]);

        return (bool) $st->fetchColumn();
    }

    /** @param array<string, mixed> $row */
    public function insert(array $row): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO inbox_messages
                (imap_uid, imap_uidvalidity, message_id, received_at, from_email, from_name, subject, body_text,
                 kind, supplier_id, status, done_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $done = $row['status'] === 'erledigt';
        $st->execute([
            $row['imap_uid'], $row['imap_uidvalidity'], mb_substr((string) $row['message_id'], 0, 255),
            $row['received_at'], mb_substr((string) $row['from_email'], 0, 255), mb_substr((string) $row['from_name'], 0, 255),
            mb_substr((string) $row['subject'], 0, 512), $row['body_text'], $row['kind'], $row['supplier_id'],
            $row['status'], $done ? date('Y-m-d H:i:s') : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param list<array{name:string, file:string, mime:string, size:int}> $attachments */
    public function setAttachments(int $id, array $attachments): void
    {
        $this->pdo->prepare('UPDATE inbox_messages SET attachments = ? WHERE id = ?')
            ->execute([json_encode($attachments, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $id]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT m.*, s.name AS supplier_name FROM inbox_messages m LEFT JOIN suppliers s ON s.id = m.supplier_id WHERE m.id = ?'
        );
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ? self::hydrate($row) : null;
    }

    /**
     * @param string $filter neu | belege | alle
     * @return list<array<string, mixed>>
     */
    public function list(string $filter, int $limit = 200): array
    {
        $where = match ($filter) {
            'neu' => "m.status = 'neu' AND m.kind <> 'beleg'",
            'belege' => "m.kind = 'beleg'",
            default => '1 = 1',
        };
        $order = $filter === 'belege' ? "m.status = 'neu' DESC, m.received_at DESC" : 'm.received_at DESC';
        $rows = $this->pdo->query(
            "SELECT m.*, s.name AS supplier_name FROM inbox_messages m LEFT JOIN suppliers s ON s.id = m.supplier_id
             WHERE {$where} ORDER BY {$order} LIMIT " . max(1, $limit)
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return list<array<string, mixed>> */
    public function openReceipts(): array
    {
        $rows = $this->pdo->query(
            "SELECT m.*, NULL AS supplier_name FROM inbox_messages m WHERE m.kind = 'beleg' AND m.status = 'neu' ORDER BY m.received_at"
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map([self::class, 'hydrate'], $rows);
    }

    /**
     * Zahlen für Startseite/Navigation.
     *
     * @return array{lieferant:int, sonstiges:int, belege:int, suppliers:list<string>}
     */
    public function counts(): array
    {
        $out = ['lieferant' => 0, 'sonstiges' => 0, 'belege' => 0, 'suppliers' => []];
        foreach ($this->pdo->query(
            "SELECT kind, COUNT(*) FROM inbox_messages WHERE status = 'neu' GROUP BY kind"
        )->fetchAll(PDO::FETCH_KEY_PAIR) as $kind => $n) {
            if ($kind === 'beleg') {
                $out['belege'] = (int) $n;
            } elseif (isset($out[$kind])) {
                $out[$kind] = (int) $n;
            }
        }
        $out['suppliers'] = array_map('strval', $this->pdo->query(
            "SELECT DISTINCT COALESCE(s.name, NULLIF(m.from_name, ''), m.from_email) FROM inbox_messages m
             LEFT JOIN suppliers s ON s.id = m.supplier_id
             WHERE m.status = 'neu' AND m.kind = 'lieferant' ORDER BY 1 LIMIT 5"
        )->fetchAll(PDO::FETCH_COLUMN));

        return $out;
    }

    public function setStatus(int $id, string $status): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE inbox_messages SET status = ?, done_at = IF(? = 'erledigt', NOW(), NULL) WHERE id = ?"
        );
        $st->execute([$status, $status, $id]);

        return $st->rowCount() > 0;
    }

    public function setKind(int $id, string $kind): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE inbox_messages SET kind = ?, status = 'neu', done_at = NULL WHERE id = ?"
        );
        $st->execute([$kind, $id]);

        return $st->rowCount() > 0;
    }

    public function setClassification(int $id, string $kind, ?int $supplierId): void
    {
        $this->pdo->prepare(
            "UPDATE inbox_messages SET kind = ?, supplier_id = ?,
                status = IF(? = 'nachtrag', 'erledigt', status),
                done_at = IF(? = 'nachtrag', COALESCE(done_at, NOW()), done_at)
             WHERE id = ?"
        )->execute([$kind, $supplierId, $kind, $kind, $id]);
    }

    /**
     * Belege nach dem Einspielen eines Pflegepakets abhaken.
     *
     * @param list<int> $ids
     */
    public function markReceiptsDone(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo->prepare(
            "UPDATE inbox_messages SET status = 'erledigt', done_at = NOW() WHERE kind = 'beleg' AND status = 'neu' AND id IN ({$in})"
        );
        $st->execute($ids);

        return $st->rowCount();
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): array
    {
        $att = is_string($row['attachments'] ?? null) ? json_decode($row['attachments'], true) : null;
        $row['attachments'] = is_array($att) ? $att : [];
        $row['id'] = (int) $row['id'];
        $row['supplier_id'] = $row['supplier_id'] !== null ? (int) $row['supplier_id'] : null;

        return $row;
    }
}
