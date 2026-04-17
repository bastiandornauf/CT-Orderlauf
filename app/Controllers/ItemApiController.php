<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Middleware\AuthMiddleware;
use App\Repositories\ItemRepository;

final class ItemApiController
{
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

        $err = Validator::required(['name' => $name], 'name');
        if ($locId <= 0) {
            $err = $err ?? 'Lagerort wählen.';
        }
        if ($err !== null) {
            Response::jsonError($err, 422);
        }

        $items->update($id, $name, $unit, $locId, $min, $max, $active, $sortOrder);

        $pairs = [];
        $rawLinks = $data['supplier_links'] ?? null;
        if (is_array($rawLinks)) {
            foreach ($rawLinks as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $sid = (int) ($row['supplier_id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }
                $pairs[] = [
                    'supplier_id' => $sid,
                    'priority' => (int) ($row['priority'] ?? 0),
                ];
            }
        }
        $items->setSupplierLinks($id, $pairs);

        $saved = $items->find($id);
        if ($saved === null) {
            Response::jsonError('Artikel nach Speichern nicht lesbar.', 500);
        }

        $linkRows = $items->supplierLinksForItem($id);
        $normLinks = [];
        foreach ($linkRows as $l) {
            $normLinks[] = [
                'item_id' => (int) $l['item_id'],
                'supplier_id' => (int) $l['supplier_id'],
                'priority' => (int) $l['priority'],
            ];
        }

        Response::jsonOk([
            'item' => [
                'id' => (int) $saved['id'],
                'name' => (string) $saved['name'],
                'unit' => (string) ($saved['unit'] ?? ''),
                'location_id' => (int) $saved['location_id'],
                'sort_order' => (int) ($saved['sort_order'] ?? 0),
                'min_stock' => $saved['min_stock'] !== null && $saved['min_stock'] !== ''
                    ? (int) $saved['min_stock'] : null,
                'max_stock' => $saved['max_stock'] !== null && $saved['max_stock'] !== ''
                    ? (int) $saved['max_stock'] : null,
                'active' => (int) ($saved['active'] ?? 0),
            ],
            'item_supplier_links' => $normLinks,
        ]);
    }
}
