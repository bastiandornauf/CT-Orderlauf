<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use PDO;

/**
 * Gemeinsame Sammlung neuer Artikel aus Freitext-Erfassung.
 *
 * Alle Nutzer zahlen in denselben Topf ein; ausgewertet wird sie nur von
 * Stammdaten-Berechtigten. Zusammengefasst wird über `dedupe_key`, damit
 * mehrfach getippte Bezeichnungen als „Regulars“ erkennbar werden.
 */
final class PendingItemRepository
{
    public const STATUS_OPEN = 'open';
    public const STATUS_DISMISSED = 'dismissed';

    public static function dedupeKey(string $name): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
        return mb_strtolower($normalized, 'UTF-8');
    }

    /**
     * Eine oder mehrere Sichtungen eines Freitext-Artikels verbuchen.
     *
     * @param array{
     *   name: string, unit?: string, quantity?: string,
     *   location_id?: int|null, supplier_id?: int|null,
     *   source?: string, increment?: int,
     *   first_seen_at?: string, last_seen_at?: string
     * } $data
     */
    public function record(array $data, ?int $userId): void
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) ($data['name'] ?? ''))) ?? '';
        if ($name === '') {
            return;
        }
        $key = self::dedupeKey($name);
        $increment = max(1, (int) ($data['increment'] ?? 1));
        $source = ($data['source'] ?? 'order') === 'inventory' ? 'inventory' : 'order';
        $firstSeen = self::normalizeDateTime($data['first_seen_at'] ?? null);
        $lastSeen = self::normalizeDateTime($data['last_seen_at'] ?? null);

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO pending_items
                (dedupe_key, name, unit, last_quantity, location_id, supplier_id,
                 source_order, source_inventory, seen_count,
                 first_seen_at, last_seen_at, last_seen_by, status, dismissed_at)
             VALUES (:key, :name, :unit, :qty, :loc, :sup, :src_o, :src_i, :cnt,
                     :first_seen, :last_seen, :user, :status, NULL)
             ON DUPLICATE KEY UPDATE
                unit = IF(pending_items.unit = \'\', VALUES(unit), pending_items.unit),
                last_quantity = IF(VALUES(last_quantity) = \'\', pending_items.last_quantity, VALUES(last_quantity)),
                location_id = COALESCE(pending_items.location_id, VALUES(location_id)),
                supplier_id = COALESCE(pending_items.supplier_id, VALUES(supplier_id)),
                source_order = GREATEST(pending_items.source_order, VALUES(source_order)),
                source_inventory = GREATEST(pending_items.source_inventory, VALUES(source_inventory)),
                seen_count = pending_items.seen_count + VALUES(seen_count),
                first_seen_at = LEAST(pending_items.first_seen_at, VALUES(first_seen_at)),
                last_seen_at = GREATEST(pending_items.last_seen_at, VALUES(last_seen_at)),
                last_seen_by = VALUES(last_seen_by),
                -- Verworfenes kommt zurück, wenn es danach erneut getippt wurde.
                status = IF(pending_items.dismissed_at IS NOT NULL
                            AND VALUES(last_seen_at) > pending_items.dismissed_at,
                            \'open\', pending_items.status),
                dismissed_at = IF(pending_items.dismissed_at IS NOT NULL
                                  AND VALUES(last_seen_at) > pending_items.dismissed_at,
                                  NULL, pending_items.dismissed_at)'
        );
        $stmt->execute([
            'key' => $key,
            'name' => $name,
            'unit' => trim((string) ($data['unit'] ?? '')),
            'qty' => trim((string) ($data['quantity'] ?? '')),
            'loc' => self::positiveIntOrNull($data['location_id'] ?? null),
            'sup' => self::positiveIntOrNull($data['supplier_id'] ?? null),
            'src_o' => $source === 'order' ? 1 : 0,
            'src_i' => $source === 'inventory' ? 1 : 0,
            'cnt' => $increment,
            'first_seen' => $firstSeen,
            'last_seen' => $lastSeen,
            'user' => $userId,
            'status' => self::STATUS_OPEN,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function allOpen(): array
    {
        $sql = 'SELECT p.*, l.name AS location_name, s.name AS supplier_name,
                       COALESCE(NULLIF(u.display_name, \'\'), u.username) AS last_seen_by_name
                FROM pending_items p
                LEFT JOIN locations l ON l.id = p.location_id
                LEFT JOIN suppliers s ON s.id = p.supplier_id
                LEFT JOIN users u ON u.id = p.last_seen_by
                WHERE p.status = ?
                ORDER BY p.seen_count DESC, p.name ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([self::STATUS_OPEN]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countOpen(): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM pending_items WHERE status = ?');
        $stmt->execute([self::STATUS_OPEN]);
        return (int) $stmt->fetchColumn();
    }

    /** Übernommen – der Eintrag hat seinen Zweck erfüllt. */
    public function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM pending_items WHERE id = ?')->execute([$id]);
    }

    /**
     * Verworfen – bleibt als Merker liegen, kehrt bei erneuter Erfassung zurück.
     *
     * Der Zeitstempel kommt bewusst aus PHP und nicht aus MySQL `NOW()`:
     * `last_seen_at` wird ebenfalls von PHP geschrieben, und nur wenn beide aus
     * derselben Uhr stammen, ist der Vergleich beim Wiederauftauchen belastbar.
     * Laufen die Zeitzonen von PHP und MySQL auseinander – auf Shared Hosting
     * keine Seltenheit –, würde jede spätere Erfassung Verworfenes sofort
     * zurückholen und „Verwerfen" wirkte kaputt.
     */
    public function dismiss(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE pending_items SET status = ?, dismissed_at = ? WHERE id = ?'
        );
        $stmt->execute([self::STATUS_DISMISSED, self::normalizeDateTime(null), $id]);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM pending_items WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function positiveIntOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;
        return $n > 0 ? $n : null;
    }

    /** Clientseitige ISO-Zeitstempel auf MySQL-DATETIME bringen, Zukunft abschneiden. */
    private static function normalizeDateTime(mixed $iso): string
    {
        $now = new \DateTimeImmutable('now');
        if (!is_string($iso) || trim($iso) === '') {
            return $now->format('Y-m-d H:i:s');
        }
        try {
            $dt = new \DateTimeImmutable($iso);
        } catch (\Exception) {
            return $now->format('Y-m-d H:i:s');
        }
        if ($dt > $now) {
            $dt = $now;
        }
        return $dt->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }
}
