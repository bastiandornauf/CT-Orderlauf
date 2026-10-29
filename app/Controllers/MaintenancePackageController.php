<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Database;
use App\Helpers\Response;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Repositories\PriceRepository;
use App\Services\MaintenancePackageService;
use PDO;

/** Pflegepaket einspielen (Vorschau + Übernahme) und Stammdaten-Export als Grundlage dafür */
final class MaintenancePackageController
{
    public function index(): void
    {
        AuthMiddleware::requireEditor();
        View::layout('layout', 'pages/maintenance/package', [
            'title' => 'Pflegepaket einspielen',
            'csrf' => Csrf::token(),
        ]);
    }

    public function preview(): void
    {
        AuthMiddleware::requireEditor();
        $pkg = self::package();
        Response::jsonOk([
            'title' => (string) ($pkg['title'] ?? ''),
            'rows' => (new MaintenancePackageService())->preview($pkg),
        ]);
    }

    public function apply(): void
    {
        AuthMiddleware::requireEditor();
        $data = self::body();
        $pkg = self::package($data);
        $selected = is_array($data['selected'] ?? null) ? $data['selected'] : [];
        if ($selected === []) {
            Response::jsonError('Keine Zeile ausgewählt.', 422);
        }
        try {
            $result = (new MaintenancePackageService())->apply($pkg, $selected, self::userId());
        } catch (\InvalidArgumentException $e) {
            Response::jsonError($e->getMessage() . ' – nichts übernommen.', 422);
        }
        Response::jsonOk($result);
    }

    /** GET /pflege/export – aktueller Artikelstamm als JSON (Grundlage für Pflegepakete) */
    public function export(): void
    {
        AuthMiddleware::requireEditor();
        $pdo = Database::pdo();
        $q = static fn (string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $latest = [];
        foreach ($q("SELECT p.*, sa.item_id, sa.supplier_id, sa.pack_size, sa.pack_unit, sa.order_unit, i.unit AS item_unit
                     FROM supplier_article_prices p JOIN supplier_articles sa ON sa.id = p.supplier_article_id JOIN items i ON i.id = sa.item_id
                     ORDER BY p.price_date DESC, p.id DESC") as $r) {
            $latest[$r['supplier_article_id'] . ':' . $r['price_type']] ??= PriceRepository::present($r);
        }
        $articles = [];
        foreach ($q('SELECT id, item_id, supplier_id, supplier_label, supplier_sku, ean, brand, pack_size, pack_unit, order_unit, is_default
                     FROM supplier_articles WHERE active = 1') as $a) {
            $a['latest_known'] = $latest[$a['id'] . ':known'] ?? null;
            $a['latest_purchase'] = $latest[$a['id'] . ':purchase'] ?? null;
            $articles[] = $a;
        }
        $export = [
            'format' => 'orderlauf-stammdaten',
            'version' => 1,
            'exported_at' => (new \DateTimeImmutable())->format('c'),
            'suppliers' => $q('SELECT id, name, has_skus, active FROM suppliers ORDER BY name'),
            'locations' => $q('SELECT id, name, active FROM locations ORDER BY sort_order'),
            'items' => $q('SELECT id, name, alias, unit, base_unit, unit_factor, location_id, active, merged_into_id FROM items ORDER BY id'),
            'item_supplier' => $q('SELECT item_id, supplier_id, priority FROM item_supplier ORDER BY item_id, priority DESC'),
            'supplier_articles' => $articles,
        ];
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="orderlauf-stammdaten-' . date('Y-m-d') . '.json"');
        echo json_encode($export, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        exit;
    }

    /** @return array<string, mixed> */
    private static function body(): array
    {
        static $data = null;
        if ($data === null) {
            $data = json_decode(file_get_contents('php://input') ?: '', true);
            if (!is_array($data)) {
                Response::jsonError('Ungültiger JSON-Body.', 400);
            }
            if (!Csrf::validate($data['_csrf'] ?? null)) {
                Response::jsonError('CSRF ungültig.', 403);
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private static function package(?array $data = null): array
    {
        $pkg = ($data ?? self::body())['package'] ?? null;
        $errors = MaintenancePackageService::validateEnvelope($pkg);
        if ($errors !== []) {
            Response::jsonError(implode(' ', $errors), 422);
        }

        return $pkg;
    }

    private static function userId(): ?int
    {
        $id = (int) ($_SESSION['user_id'] ?? 0);

        return $id > 0 ? $id : null;
    }
}
