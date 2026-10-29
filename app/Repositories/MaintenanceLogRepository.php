<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;

/** Protokoll für Pflegeaktionen mit Auswirkungen (Preis gelöscht, Artikel zusammengeführt, …). */
final class MaintenanceLogRepository
{
    /** @param array<string, mixed> $detail */
    public function log(string $action, ?int $itemId, array $detail, ?int $userId): void
    {
        Database::pdo()->prepare(
            'INSERT INTO maintenance_log (action, item_id, detail_json, user_id, created_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $action,
            $itemId,
            json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $userId,
            // Zeit aus PHP, nicht aus MySQL NOW() – gleiche Zeitzone wie der Rest der App
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
