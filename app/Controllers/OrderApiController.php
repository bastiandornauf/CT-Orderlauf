<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Database;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\SettingsRepository;
use App\Services\PdfService;
use DateTimeImmutable;
use PDO;

final class OrderApiController
{
    public function payload(): void
    {
        AuthMiddleware::requireAuth();
        $date = (string) ($_GET['target_date'] ?? '');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($dt === false) {
            Response::jsonError('target_date muss YYYY-MM-DD sein.', 422);
        }
        $weekday = (int) $dt->format('N');

        $locRepo = new LocationRepository();
        $supRepo = new SupplierRepository();
        $itemRepo = new ItemRepository();
        $settings = new SettingsRepository();

        $locations = $locRepo->all(true);
        $suppliers = $supRepo->all(true);
        $deliveryRows = Database::pdo()->query(
            'SELECT supplier_id, weekday FROM supplier_delivery_days ORDER BY supplier_id, weekday'
        )->fetchAll(PDO::FETCH_ASSOC);

        $items = Database::pdo()->query(
            'SELECT id, name, unit, location_id, active FROM items WHERE active = 1 ORDER BY name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $links = Database::pdo()->query(
            'SELECT item_id, supplier_id, priority FROM item_supplier ORDER BY item_id, priority DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $deliveringIds = [];
        foreach ($suppliers as $s) {
            $days = $supRepo->deliveryWeekdays((int) $s['id']);
            if (in_array($weekday, $days, true)) {
                $deliveringIds[] = (int) $s['id'];
            }
        }

        Response::jsonOk([
            'target_date' => $date,
            'target_weekday' => $weekday,
            'suppliers_delivering_ids' => $deliveringIds,
            'locations' => $locations,
            'suppliers' => $suppliers,
            'supplier_delivery_days' => $deliveryRows,
            'items' => $items,
            'item_supplier_links' => $links,
            'settings' => [
                'order_cc_email' => $settings->get('order_cc_email', ''),
                'app_name' => $settings->get('app_name', 'CT-Orderlauf'),
            ],
        ]);
    }

    public function pdf(): void
    {
        AuthMiddleware::requireAuth();
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Response::jsonError('Ungültiger JSON-Body.', 400);
        }
        if (!Csrf::validate($data['_csrf'] ?? null)) {
            Response::jsonError('CSRF ungültig.', 403);
        }
        $supplierName = trim((string) ($data['supplier_name'] ?? ''));
        $targetDate = trim((string) ($data['target_date'] ?? ''));
        $lines = $data['lines'] ?? [];
        if ($supplierName === '' || !is_array($lines)) {
            Response::jsonError('supplier_name und lines erforderlich.', 422);
        }
        /** @var list<array{label: string, quantity: string, unit: string}> $norm */
        $norm = [];
        foreach ($lines as $l) {
            if (!is_array($l)) {
                continue;
            }
            $norm[] = [
                'label' => (string) ($l['label'] ?? ''),
                'quantity' => (string) ($l['quantity'] ?? ''),
                'unit' => (string) ($l['unit'] ?? ''),
            ];
        }
        $note = (string) ($data['note'] ?? '');
        $settings = new SettingsRepository();
        $app = $settings->get('app_name', 'CT-Orderlauf');
        $pdf = new PdfService();
        $bin = $pdf->renderOrderPdf(
            $app . ' – Bestellung',
            $supplierName,
            $targetDate,
            $norm,
            $note
        );
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="bestellung-' . preg_replace('/[^a-z0-9_-]+/i', '_', $supplierName) . '.pdf"');
        echo $bin;
        exit;
    }
}
