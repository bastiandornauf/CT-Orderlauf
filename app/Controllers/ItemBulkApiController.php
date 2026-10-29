<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Database;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\MaintenanceLogRepository;
use App\Services\ItemQualityService;
use PDO;

/** Sammelbearbeitung mehrerer Artikel aus der Artikelliste. Jede Aktion wird mit Vorher-Stand protokolliert. */
final class ItemBulkApiController
{
    public const ACTIONS = ['apply_unit_suggestion', 'set_unit', 'set_location', 'archive', 'activate', 'add_supplier'];

    public function run(): void
    {
        AuthMiddleware::requireEditor();
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($data)) {
            Response::jsonError('Ungültiger JSON-Body.', 400);
        }
        if (!Csrf::validate($data['_csrf'] ?? null)) {
            Response::jsonError('CSRF ungültig.', 403);
        }
        $action = (string) ($data['action'] ?? '');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($data['ids'] ?? [])), static fn (int $i): bool => $i > 0)));
        if (!in_array($action, self::ACTIONS, true)) {
            Response::jsonError('Unbekannte Aktion.', 422);
        }
        if ($ids === []) {
            Response::jsonError('Keine Artikel ausgewählt.', 422);
        }
        $value = $data['value'] ?? null;
        $pdo = Database::pdo();
        $in = implode(',', array_fill(0, count($ids), '?'));

        $pdo->beginTransaction();
        try {
            $before = $pdo->prepare("SELECT id, name, unit, location_id, active FROM items WHERE id IN ($in)");
            $before->execute($ids);
            $beforeRows = $before->fetchAll(PDO::FETCH_ASSOC);
            $changed = match ($action) {
                'apply_unit_suggestion' => $this->applyUnitSuggestions($ids),
                'set_unit' => $this->update("UPDATE items SET unit = ? WHERE id IN ($in)", [self::text($value, 64), ...$ids]),
                'set_location' => $this->setLocation((int) $value, $ids, $in),
                'archive' => $this->update("UPDATE items SET active = 0 WHERE id IN ($in)", $ids),
                'activate' => $this->update("UPDATE items SET active = 1 WHERE id IN ($in) AND merged_into_id IS NULL", $ids),
                'add_supplier' => $this->addSupplier($data, $ids),
            };
            (new MaintenanceLogRepository())->log('bulk_' . $action, null, ['value' => $value, 'before' => $beforeRows], self::userId());
            $pdo->commit();
        } catch (\InvalidArgumentException $e) {
            $pdo->rollBack();
            Response::jsonError($e->getMessage(), 422);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Response::jsonOk(['changed' => $changed]);
    }

    private function update(string $sql, array $params): int
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /** Übernimmt die Schreibweisen-Vorschläge der Datenpflege nur für die ausgewählten Artikel. */
    private function applyUnitSuggestions(array $ids): int
    {
        $fixes = (new ItemQualityService())->unitSpellingFixes();
        $stmt = Database::pdo()->prepare('UPDATE items SET unit = ? WHERE id = ? AND unit = ?');
        $n = 0;
        foreach ($ids as $id) {
            if (isset($fixes[$id])) {
                [$from, $to] = $fixes[$id];
                $stmt->execute([$to, $id, $from]);
                $n += $stmt->rowCount();
            }
        }

        return $n;
    }

    private function setLocation(int $locationId, array $ids, string $in): int
    {
        $ok = Database::pdo()->prepare('SELECT 1 FROM locations WHERE id = ?');
        $ok->execute([$locationId]);
        if (!$ok->fetchColumn()) {
            throw new \InvalidArgumentException('Lagerort wählen.');
        }

        return $this->update("UPDATE items SET location_id = ? WHERE id IN ($in)", [$locationId, ...$ids]);
    }

    /** Lieferant als (weitere) Bezugsquelle ergänzen; vorhandene Zuordnungen behalten ihre Priorität. */
    private function addSupplier(array $data, array $ids): int
    {
        $supplierId = (int) ($data['value'] ?? 0);
        $priority = (int) ($data['priority'] ?? 0);
        $ok = Database::pdo()->prepare('SELECT 1 FROM suppliers WHERE id = ?');
        $ok->execute([$supplierId]);
        if (!$ok->fetchColumn()) {
            throw new \InvalidArgumentException('Lieferant wählen.');
        }
        $stmt = Database::pdo()->prepare('INSERT IGNORE INTO item_supplier (item_id, supplier_id, priority) VALUES (?, ?, ?)');
        $n = 0;
        foreach ($ids as $id) {
            $stmt->execute([$id, $supplierId, $priority]);
            $n += $stmt->rowCount();
        }

        return $n;
    }

    private static function text(mixed $v, int $max): string
    {
        $s = trim((string) $v);
        if ($s === '') {
            throw new \InvalidArgumentException('Wert eingeben.');
        }

        return mb_substr($s, 0, $max);
    }

    private static function userId(): ?int
    {
        $id = (int) ($_SESSION['user_id'] ?? 0);

        return $id > 0 ? $id : null;
    }
}
