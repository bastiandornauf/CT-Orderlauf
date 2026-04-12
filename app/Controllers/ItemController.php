<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Repositories\ItemRepository;
use App\Repositories\LocationRepository;
use App\Repositories\SupplierRepository;

final class ItemController
{
    public function __construct(
        private ItemRepository $items = new ItemRepository(),
        private LocationRepository $locations = new LocationRepository(),
        private SupplierRepository $suppliers = new SupplierRepository()
    ) {
    }

    public function index(): void
    {
        AuthMiddleware::requireAuth();
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

    public function form(): void
    {
        AuthMiddleware::requireAuth();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $row = $id ? $this->items->find($id) : null;
        if ($id && $row === null) {
            Response::redirect('/items');
            return;
        }
        $links = $id ? $this->items->supplierLinksWithNames($id) : [];
        View::layout('layout', 'pages/items/form', [
            'title' => $id ? 'Artikel bearbeiten' : 'Artikel anlegen',
            'item' => $row,
            'locations' => $this->locations->all(true),
            'suppliers' => $this->suppliers->all(true),
            'links' => $links,
            'csrf' => Csrf::token(),
        ]);
    }

    public function save(): void
    {
        AuthMiddleware::requireAuth();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/items');
            return;
        }
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = trim((string) ($_POST['name'] ?? ''));
        $unit = trim((string) ($_POST['unit'] ?? ''));
        $locId = (int) ($_POST['location_id'] ?? 0);
        $min = ($_POST['min_stock'] ?? '') === '' ? null : (int) $_POST['min_stock'];
        $max = ($_POST['max_stock'] ?? '') === '' ? null : (int) $_POST['max_stock'];
        $active = isset($_POST['active']);

        $err = Validator::required(['name' => $name], 'name');
        if ($locId <= 0) {
            $err = $err ?? 'Lagerort wählen.';
        }
        if ($err) {
            $this->renderFormError($id, $name, $unit, $locId, $min, $max, $active, $err);
            return;
        }

        if ($id > 0) {
            $this->items->update($id, $name, $unit, $locId, $min, $max, $active);
        } else {
            $id = $this->items->create($name, $unit, $locId, $min, $max, $active);
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
        Response::redirect('/items');
    }

    /** @param list<array{supplier_id:int,priority:int}> $pairs placeholder */
    private function renderFormError(
        int $id,
        string $name,
        string $unit,
        int $locId,
        ?int $min,
        ?int $max,
        bool $active,
        string $error
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
                'active' => $active ? 1 : 0,
            ],
            'locations' => $this->locations->all(true),
            'suppliers' => $this->suppliers->all(true),
            'links' => $id ? $this->items->supplierLinksWithNames($id) : [],
            'error' => $error,
            'csrf' => Csrf::token(),
        ]);
    }
}
