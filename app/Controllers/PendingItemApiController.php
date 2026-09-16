<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\PendingItemRepository;

/**
 * Gemeinsame Sammlung neuer Artikel.
 *
 * `sync` steht **allen** angemeldeten Rollen offen – auch „Nur Bestellen“ muss
 * einzahlen können, sonst fehlen genau die Freitext-Artikel der Kollegen.
 * Lesen und Abarbeiten bleibt Stammdaten-Berechtigten vorbehalten.
 */
final class PendingItemApiController
{
    private const MAX_SYNC_ITEMS = 500;

    public function __construct(
        private PendingItemRepository $pending = new PendingItemRepository()
    ) {
    }

    public function sync(): void
    {
        AuthMiddleware::requireAuth();
        $data = $this->jsonBody();

        $items = $data['items'] ?? null;
        if (!is_array($items)) {
            Response::jsonError('items muss eine Liste sein.', 422);
        }
        if (count($items) > self::MAX_SYNC_ITEMS) {
            Response::jsonError('Zu viele Einträge in einem Aufruf.', 422);
        }

        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $saved = 0;
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $this->pending->record([
                'name' => $name,
                'unit' => (string) ($row['unit'] ?? ''),
                'quantity' => (string) ($row['quantity'] ?? ''),
                'location_id' => $row['location_id'] ?? null,
                'supplier_id' => $row['supplier_id'] ?? null,
                'source' => (string) ($row['source'] ?? 'order'),
                'increment' => (int) ($row['increment'] ?? 1),
                'first_seen_at' => $row['first_seen_at'] ?? null,
                'last_seen_at' => $row['last_seen_at'] ?? null,
            ], $userId);
            $saved++;
        }

        Response::jsonOk(['saved' => $saved]);
    }

    public function index(): void
    {
        AuthMiddleware::requireEditor();
        $rows = [];
        foreach ($this->pending->allOpen() as $r) {
            $rows[] = [
                'id' => (int) $r['id'],
                'name' => (string) $r['name'],
                'unit' => (string) ($r['unit'] ?? ''),
                'last_quantity' => (string) ($r['last_quantity'] ?? ''),
                'location_id' => $r['location_id'] !== null ? (int) $r['location_id'] : null,
                'location_name' => (string) ($r['location_name'] ?? ''),
                'supplier_id' => $r['supplier_id'] !== null ? (int) $r['supplier_id'] : null,
                'supplier_name' => (string) ($r['supplier_name'] ?? ''),
                'source_order' => (int) $r['source_order'] === 1,
                'source_inventory' => (int) $r['source_inventory'] === 1,
                'seen_count' => (int) $r['seen_count'],
                'first_seen_at' => (string) $r['first_seen_at'],
                'last_seen_at' => (string) $r['last_seen_at'],
                'last_seen_by_name' => (string) ($r['last_seen_by_name'] ?? ''),
            ];
        }
        Response::jsonOk(['items' => $rows]);
    }

    public function resolve(): void
    {
        AuthMiddleware::requireEditor();
        $data = $this->jsonBody();

        $id = (int) ($data['id'] ?? 0);
        $action = (string) ($data['action'] ?? '');
        if ($id <= 0) {
            Response::jsonError('id fehlt.', 422);
        }
        if (!in_array($action, ['transferred', 'dismiss'], true)) {
            Response::jsonError('action muss transferred oder dismiss sein.', 422);
        }
        if ($this->pending->find($id) === null) {
            Response::jsonError('Eintrag nicht gefunden.', 404);
        }

        if ($action === 'transferred') {
            $this->pending->delete($id);
        } else {
            $this->pending->dismiss($id);
        }

        Response::jsonOk(['open_count' => $this->pending->countOpen()]);
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Response::jsonError('Ungültiger JSON-Body.', 400);
        }
        if (!Csrf::validate($data['_csrf'] ?? null)) {
            Response::jsonError('CSRF ungültig.', 403);
        }
        return $data;
    }
}
