<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Database;
use App\Helpers\ItemDisplay;
use App\Helpers\ItemSupplierInput;
use App\Helpers\Response;
use App\Helpers\ValuationPrice;
use App\Helpers\Validator;
use App\Middleware\AuthMiddleware;
use App\Repositories\ItemRepository;
use App\Repositories\PriceRepository;
use App\Repositories\SupplierArticleRepository;

final class ItemApiController
{
    public function create(): void
    {
        AuthMiddleware::requireEditor();
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Response::jsonError('Ungültiger JSON-Body.', 400);
        }
        if (!Csrf::validate($data['_csrf'] ?? null)) {
            Response::jsonError('CSRF ungültig.', 403);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $alias = trim((string) ($data['alias'] ?? ''));
        $unit = trim((string) ($data['unit'] ?? ''));
        $locId = (int) ($data['location_id'] ?? 0);
        $valuationPrice = ValuationPrice::parse($data['valuation_price'] ?? null);

        $err = Validator::required(['name' => $name], 'name');
        if ($locId <= 0) {
            $err = $err ?? 'Lagerort wählen.';
        }
        if ($err !== null) {
            Response::jsonError($err, 422);
        }

        $items = new ItemRepository();
        $existing = $items->findByName($name);
        if ($existing !== null) {
            Response::jsonError('Artikel mit diesem Namen existiert bereits.', 409);
        }

        $supplierInput = ItemSupplierInput::parse(is_array($data['supplier_links'] ?? null) ? $data['supplier_links'] : []);
        $id = self::inTransaction(static function () use ($items, $name, $alias, $unit, $locId, $valuationPrice, $supplierInput): int {
            $id = $items->create($name, $unit, $locId, null, null, true, 0, $valuationPrice);
            $items->updateAlias($id, $alias);
            if ($supplierInput['pairs'] !== []) {
                $items->setSupplierLinks($id, $supplierInput['pairs']);
            }
            if ($supplierInput['articles'] !== null) {
                (new SupplierArticleRepository())->saveDefaults($id, $supplierInput['articles']);
            }

            return $id;
        });

        self::respondWithItem($items, $id, 'Artikel nach Anlegen nicht lesbar.');
    }

    public function save(): void
    {
        AuthMiddleware::requireEditor();
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Response::jsonError('Ungültiger JSON-Body.', 400);
        }
        if (!Csrf::validate($data['_csrf'] ?? null)) {
            Response::jsonError('CSRF ungültig.', 403);
        }

        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            Response::jsonError('Nur Bearbeiten bestehender Artikel.', 422);
        }

        $items = new ItemRepository();
        $existing = $items->find($id);
        if ($existing === null) {
            Response::jsonError('Artikel nicht gefunden.', 404);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $unit = trim((string) ($data['unit'] ?? ''));
        $locId = (int) ($data['location_id'] ?? 0);
        $minRaw = $data['min_stock'] ?? null;
        $maxRaw = $data['max_stock'] ?? null;
        $min = ($minRaw === null || $minRaw === '') ? null : (int) $minRaw;
        $max = ($maxRaw === null || $maxRaw === '') ? null : (int) $maxRaw;
        $sortOrder = (int) ($data['sort_order'] ?? 0);
        $active = !empty($data['active']);
        $valuationPrice = ValuationPrice::parse($data['valuation_price'] ?? null);

        $err = Validator::required(['name' => $name], 'name');
        if ($locId <= 0) {
            $err = $err ?? 'Lagerort wählen.';
        }
        if ($err !== null) {
            Response::jsonError($err, 422);
        }

        $supplierInput = ItemSupplierInput::parse(is_array($data['supplier_links'] ?? null) ? $data['supplier_links'] : []);
        self::inTransaction(static function () use ($items, $id, $data, $name, $unit, $locId, $min, $max, $active, $sortOrder, $valuationPrice, $supplierInput): void {
            $items->update($id, $name, $unit, $locId, $min, $max, $active, $sortOrder, $valuationPrice);
            // Alias nur ändern, wenn mitgeschickt (ältere Clients kennen das Feld nicht)
            if (array_key_exists('alias', $data)) {
                $items->updateAlias($id, (string) ($data['alias'] ?? ''));
            }
            if (array_key_exists('unit_factor', $data)) {
                $items->updateUnitConversion($id, $data['unit_factor'], $data['base_unit'] ?? null);
            }
            $items->setSupplierLinks($id, $supplierInput['pairs']);
            if ($supplierInput['articles'] !== null) {
                (new SupplierArticleRepository())->saveDefaults($id, $supplierInput['articles']);
            }
        });

        self::respondWithItem($items, $id, 'Artikel nach Speichern nicht lesbar.');
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    private static function inTransaction(callable $fn): mixed
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function respondWithItem(ItemRepository $items, int $id, string $notFound): never
    {
        $saved = $items->find($id);
        if ($saved === null) {
            Response::jsonError($notFound, 500);
        }
        $articles = (new SupplierArticleRepository())->defaultsByItemIds([$id])[$id] ?? [];
        $prices = (new PriceRepository())->latestForDefaults($id)[$id] ?? [];
        $normLinks = [];
        foreach ($items->supplierLinksForItem($id) as $l) {
            $sid = (int) $l['supplier_id'];
            $normLinks[] = [
                'item_id' => (int) $l['item_id'],
                'supplier_id' => $sid,
                'priority' => (int) $l['priority'],
            ] + ItemRepository::articleFields($articles[$sid] ?? null) + ['price' => $prices[$sid] ?? null];
        }

        Response::jsonOk([
            'item' => [
                'id' => (int) $saved['id'],
                'name' => (string) $saved['name'],
                'alias' => $saved['alias'] ?? null,
                'display_name' => ItemDisplay::name($saved),
                'unit_factor' => $saved['unit_factor'] ?? null,
                'base_unit' => $saved['base_unit'] ?? null,
                'unit' => (string) ($saved['unit'] ?? ''),
                'location_id' => (int) $saved['location_id'],
                'sort_order' => (int) ($saved['sort_order'] ?? 0),
                'min_stock' => $saved['min_stock'] !== null && $saved['min_stock'] !== ''
                    ? (int) $saved['min_stock'] : null,
                'max_stock' => $saved['max_stock'] !== null && $saved['max_stock'] !== ''
                    ? (int) $saved['max_stock'] : null,
                'valuation_price' => $saved['valuation_price'] !== null && $saved['valuation_price'] !== ''
                    ? round((float) $saved['valuation_price'], 2) : null,
                'active' => (int) ($saved['active'] ?? 0),
            ],
            'item_supplier_links' => $normLinks,
        ]);
    }
}
