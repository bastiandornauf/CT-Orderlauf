<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Database;
use App\Helpers\Money;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\ItemRepository;
use App\Repositories\MaintenanceLogRepository;
use App\Repositories\PriceRepository;
use App\Repositories\SupplierArticleRepository;

final class PriceApiController
{
    /** GET /api/prices?item_id= → Preisverlauf eines Artikels (alle Lieferanten) */
    public function index(): void
    {
        AuthMiddleware::requireEditor();
        $itemId = (int) ($_GET['item_id'] ?? 0);
        if ($itemId <= 0 || (new ItemRepository())->find($itemId) === null) {
            Response::jsonError('Artikel nicht gefunden.', 404);
        }

        Response::jsonOk(['prices' => (new PriceRepository())->historyForItem($itemId)]);
    }

    /** POST /api/prices/add – neuer datierter Preis; doppelte Einträge werden erkannt */
    public function add(): void
    {
        AuthMiddleware::requireEditor();
        $data = self::jsonBody();

        $itemId = (int) ($data['item_id'] ?? 0);
        $supplierId = (int) ($data['supplier_id'] ?? 0);
        $type = (string) ($data['price_type'] ?? '');
        $perUnit = (string) ($data['per_unit'] ?? 'order');
        $source = (string) ($data['source'] ?? 'manual');
        $amount = Money::parse($data['amount'] ?? null);
        $perQty = Money::parse($data['per_qty'] ?? '1');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($data['price_date'] ?? ''));

        $err = match (true) {
            $itemId <= 0 || $supplierId <= 0 => 'Artikel und Lieferant angeben.',
            !in_array($type, PriceRepository::TYPES, true) => 'Preisart wählen.',
            $amount === null || $amount === 0 => 'Betrag eingeben (z. B. 24,80).',
            $perQty === null || $perQty === 0 => 'Menge der Preisbasis muss größer als 0 sein.',
            !in_array($perUnit, PriceRepository::PER_UNITS, true) => 'Preisbasis wählen.',
            !in_array($source, PriceRepository::SOURCES, true) => 'Herkunft wählen.',
            $date === false => 'Datum fehlt.',
            $date > new \DateTimeImmutable('tomorrow') => 'Datum liegt in der Zukunft.',
            default => null,
        };
        if ($err !== null) {
            Response::jsonError($err, 422);
        }
        $linked = array_map(
            static fn (array $l): int => (int) $l['supplier_id'],
            (new ItemRepository())->supplierLinksForItem($itemId)
        );
        if (!in_array($supplierId, $linked, true)) {
            Response::jsonError('Lieferant ist diesem Artikel nicht zugeordnet.', 422);
        }

        $text = static fn (mixed $v, int $max): ?string => trim((string) ($v ?? '')) === '' ? null : mb_substr(trim((string) $v), 0, $max);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $articleId = (new SupplierArticleRepository())->ensureDefault($itemId, $supplierId);
            $result = (new PriceRepository())->add([
                'supplier_article_id' => $articleId,
                'price_type' => $type,
                'amount' => $amount,
                'per_qty' => $perQty,
                'per_unit' => $perUnit,
                'price_date' => $date->format('Y-m-d'),
                'source' => $source,
                'document_ref' => $text($data['document_ref'] ?? null, 128),
                'note' => $text($data['note'] ?? null, 255),
                'is_net' => ($data['is_net'] ?? true) !== false,
            ], self::userId());
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Response::jsonOk([
            'created' => $result['created'],
            'prices' => (new PriceRepository())->historyForItem($itemId),
        ]);
    }

    /** POST /api/prices/delete – nur für Tippfehler; wird protokolliert */
    public function delete(): void
    {
        AuthMiddleware::requireEditor();
        $data = self::jsonBody();
        $repo = new PriceRepository();
        $row = $repo->find((int) ($data['id'] ?? 0));
        if ($row === null) {
            Response::jsonError('Preis nicht gefunden.', 404);
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            (new MaintenanceLogRepository())->log('price_deleted', (int) $row['item_id'], $row, self::userId());
            $repo->delete((int) $row['id']);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Response::jsonOk(['prices' => $repo->historyForItem((int) $row['item_id'])]);
    }

    /** @return array<string, mixed> */
    private static function jsonBody(): array
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($data)) {
            Response::jsonError('Ungültiger JSON-Body.', 400);
        }
        if (!Csrf::validate($data['_csrf'] ?? null)) {
            Response::jsonError('CSRF ungültig.', 403);
        }

        return $data;
    }

    private static function userId(): ?int
    {
        $id = (int) ($_SESSION['user_id'] ?? 0);

        return $id > 0 ? $id : null;
    }
}
