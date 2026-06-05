<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\ValuationPrice;
use App\Helpers\Validator;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Repositories\ItemRepository;
use App\Repositories\LocationRepository;
use App\Repositories\SupplierRepository;

final class ItemController
{
    /**
     * @return array{loc:int, active:string, supplier:int, q:string}
     */
    private static function parseListFilterFromGet(): array
    {
        $loc = isset($_GET['loc']) ? (int) $_GET['loc'] : 0;
        $active = (string) ($_GET['active'] ?? 'all');
        if (!in_array($active, ['all', '1', '0'], true)) {
            $active = 'all';
        }
        $supplier = isset($_GET['supplier']) ? (int) $_GET['supplier'] : 0;
        $q = trim((string) ($_GET['q'] ?? ''));

        return [
            'loc' => max(0, $loc),
            'active' => $active,
            'supplier' => max(0, $supplier),
            'q' => $q,
        ];
    }

    /**
     * @param array{loc:int, active:string, supplier:int, q:string} $f
     */
    private static function buildItemsIndexQuery(array $f): string
    {
        $params = [];
        if ($f['loc'] > 0) {
            $params['loc'] = $f['loc'];
        }
        if ($f['active'] !== 'all') {
            $params['active'] = $f['active'];
        }
        if ($f['supplier'] > 0) {
            $params['supplier'] = $f['supplier'];
        }
        if ($f['q'] !== '') {
            $params['q'] = $f['q'];
        }

        return $params === [] ? '' : '?' . http_build_query($params);
    }

    /**
     * @return array{loc:int, active:string, supplier:int, q:string}
     */
    private static function parseListFilterFromPost(): array
    {
        $loc = (int) ($_POST['list_filter_loc'] ?? 0);
        $active = (string) ($_POST['list_filter_active'] ?? 'all');
        if (!in_array($active, ['all', '1', '0'], true)) {
            $active = 'all';
        }
        $supplier = (int) ($_POST['list_filter_supplier'] ?? 0);
        $q = trim((string) ($_POST['list_filter_q'] ?? ''));

        return [
            'loc' => max(0, $loc),
            'active' => $active,
            'supplier' => max(0, $supplier),
            'q' => $q,
        ];
    }

    public function __construct(
        private ItemRepository $items = new ItemRepository(),
        private LocationRepository $locations = new LocationRepository(),
        private SupplierRepository $suppliers = new SupplierRepository()
    ) {
    }

    public function index(): void
    {
        AuthMiddleware::requireEditor();
        $locId = isset($_GET['loc']) ? (int) $_GET['loc'] : 0;
        if ($locId <= 0) {
            $locId = 0;
        }
        $active = (string) ($_GET['active'] ?? 'all');
        if (!in_array($active, ['all', '1', '0'], true)) {
            $active = 'all';
        }
        $q = trim((string) ($_GET['q'] ?? ''));
        $supplierId = isset($_GET['supplier']) ? (int) $_GET['supplier'] : 0;
        if ($supplierId <= 0) {
            $supplierId = 0;
        }

        View::layout('layout', 'pages/items/index', [
            'title' => 'Artikel',
            'items' => $this->items->allForList(
                $locId > 0 ? $locId : null,
                $active,
                $q === '' ? null : $q,
                false,
                $supplierId > 0 ? $supplierId : null
            ),
            'locations' => $this->locations->all(),
            'suppliers' => $this->suppliers->all(),
            'filter_loc' => $locId,
            'filter_active' => $active,
            'filter_supplier' => $supplierId,
            'filter_q' => $q,
            'csrf' => Csrf::token(),
        ]);
    }

    public function pending(): void
    {
        AuthMiddleware::requireEditor();
        View::layout('layout', 'pages/items/pending', [
            'title' => 'Neue Artikel',
            'locations' => $this->locations->all(true),
            'csrf' => Csrf::token(),
        ]);
    }

    public function form(): void
    {
        AuthMiddleware::requireEditor();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $row = $id ? $this->items->find($id) : null;
        if ($id && $row === null) {
            Response::redirect('/items' . self::buildItemsIndexQuery(self::parseListFilterFromGet()));
            return;
        }
        $links = $id ? $this->items->supplierLinksWithNames($id) : [];
        $listFilter = self::parseListFilterFromGet();
        View::layout('layout', 'pages/items/form', [
            'title' => $id ? 'Artikel bearbeiten' : 'Artikel anlegen',
            'item' => $row,
            'locations' => $this->locations->all(true),
            'suppliers' => $this->suppliers->all(true),
            'links' => $links,
            'list_filter' => $listFilter,
            'csrf' => Csrf::token(),
        ]);
    }

    public function save(): void
    {
        AuthMiddleware::requireEditor();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/items' . self::buildItemsIndexQuery(self::parseListFilterFromPost()));
            return;
        }
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = trim((string) ($_POST['name'] ?? ''));
        $unit = trim((string) ($_POST['unit'] ?? ''));
        $locId = (int) ($_POST['location_id'] ?? 0);
        $min = ($_POST['min_stock'] ?? '') === '' ? null : (int) $_POST['min_stock'];
        $max = ($_POST['max_stock'] ?? '') === '' ? null : (int) $_POST['max_stock'];
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']);
        $valuationPrice = ValuationPrice::parse($_POST['valuation_price'] ?? null);

        $err = Validator::required(['name' => $name], 'name');
        if ($locId <= 0) {
            $err = $err ?? 'Lagerort wählen.';
        }
        if ($err) {
            $this->renderFormError(
                $id,
                $name,
                $unit,
                $locId,
                $min,
                $max,
                $active,
                $err,
                $sortOrder,
                $valuationPrice,
                self::parseListFilterFromPost(),
            );
            return;
        }

        if ($id > 0) {
            $this->items->update($id, $name, $unit, $locId, $min, $max, $active, $sortOrder, $valuationPrice);
        } else {
            $id = $this->items->create($name, $unit, $locId, $min, $max, $active, $sortOrder, $valuationPrice);
        }

        $pairs = [];
        if (!empty($_POST['supplier_id']) && is_array($_POST['supplier_id'])) {
            foreach ($_POST['supplier_id'] as $i => $sid) {
                $sid = (int) $sid;
                if ($sid <= 0) {
                    continue;
                }
                $pr = (int) ($_POST['supplier_priority'][$i] ?? 0);
                $pairs[] = ['supplier_id' => $sid, 'priority' => $pr];
            }
        }
        $this->items->setSupplierLinks($id, $pairs);

        $_SESSION['flash_ok'] = 'Artikel gespeichert.';
        Response::redirect('/items' . self::buildItemsIndexQuery(self::parseListFilterFromPost()));
    }

    /** @param array{loc:int, active:string, supplier:int, q:string} $listFilter */
    private function renderFormError(
        int $id,
        string $name,
        string $unit,
        int $locId,
        ?int $min,
        ?int $max,
        bool $active,
        string $error,
        int $sortOrder,
        ?float $valuationPrice,
        array $listFilter
    ): void {
        View::layout('layout', 'pages/items/form', [
            'title' => $id ? 'Artikel bearbeiten' : 'Artikel anlegen',
            'item' => [
                'id' => $id ?: null,
                'name' => $name,
                'unit' => $unit,
                'location_id' => $locId,
                'min_stock' => $min,
                'max_stock' => $max,
                'valuation_price' => $valuationPrice,
                'active' => $active ? 1 : 0,
                'sort_order' => $sortOrder,
            ],
            'locations' => $this->locations->all(true),
            'suppliers' => $this->suppliers->all(true),
            'links' => $id ? $this->items->supplierLinksWithNames($id) : [],
            'list_filter' => $listFilter,
            'error' => $error,
            'csrf' => Csrf::token(),
        ]);
    }
}
