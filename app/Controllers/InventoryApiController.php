<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\LocationRepository;
use PDO;
use PDOException;

final class InventoryApiController
{
    public function payload(): void
    {
        AuthMiddleware::requireAuth();

        $stichtag = (string) ($_GET['stichtag'] ?? '');
        if ($stichtag === '') {
            $stichtag = (new \DateTimeImmutable('today'))->format('Y-m-d');
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $stichtag);
        if ($dt === false) {
            Response::jsonError('stichtag muss YYYY-MM-DD sein.', 422);
        }
        $stichtag = $dt->format('Y-m-d');

        try {
            $locRepo = new LocationRepository();
            $locations = $locRepo->all(true);
            $items = $this->fetchActiveItemsForInventory();
        } catch (PDOException $e) {
            Response::jsonError('Datenbankfehler: ' . $e->getMessage(), 500);
        }

        Response::jsonOk([
            'stichtag' => $stichtag,
            'locations' => $locations,
            'items' => $items,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function fetchActiveItemsForInventory(): array
    {
        $pdo = Database::pdo();
        $priceCol = $this->hasValuationPriceColumn($pdo)
            ? 'i.valuation_price'
            : 'NULL AS valuation_price';

        $items = $pdo->query(
            "SELECT i.id, i.name, i.unit, i.location_id, i.sort_order, {$priceCol}, i.active
             FROM items i
             JOIN locations l ON l.id = i.location_id
             WHERE i.active = 1 AND l.active = 1
             ORDER BY l.sort_order ASC, i.sort_order ASC, i.name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$row) {
            $row['id'] = (int) $row['id'];
            $row['location_id'] = (int) $row['location_id'];
            $row['sort_order'] = (int) ($row['sort_order'] ?? 0);
            $row['active'] = (int) ($row['active'] ?? 0);
            if ($row['valuation_price'] !== null && $row['valuation_price'] !== '') {
                $row['valuation_price'] = round((float) $row['valuation_price'], 2);
            } else {
                $row['valuation_price'] = null;
            }
        }
        unset($row);

        return $items;
    }

    private function hasValuationPriceColumn(PDO $pdo): bool
    {
        $stmt = $pdo->query("SHOW COLUMNS FROM items LIKE 'valuation_price'");

        return $stmt->fetch() !== false;
    }
}
