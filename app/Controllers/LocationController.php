<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Repositories\LocationRepository;

final class LocationController
{
    public function __construct(
        private LocationRepository $repo = new LocationRepository()
    ) {
    }

    public function index(): void
    {
        AuthMiddleware::requireAuth();
        View::layout('layout', 'pages/locations/index', [
            'title' => 'Lagerorte',
            'locations' => $this->repo->all(),
            'csrf' => Csrf::token(),
        ]);
    }

    public function form(): void
    {
        AuthMiddleware::requireAuth();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $row = $id ? $this->repo->find($id) : null;
        if ($id && $row === null) {
            Response::redirect('/locations');
            return;
        }
        View::layout('layout', 'pages/locations/form', [
            'title' => $id ? 'Lagerort bearbeiten' : 'Lagerort anlegen',
            'location' => $row,
            'csrf' => Csrf::token(),
        ]);
    }

    public function save(): void
    {
        AuthMiddleware::requireAuth();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/locations');
            return;
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']);
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        $err = Validator::required(['name' => $name], 'name');
        if ($err) {
            View::layout('layout', 'pages/locations/form', [
                'title' => $id ? 'Lagerort bearbeiten' : 'Lagerort anlegen',
                'location' => array_merge($id ? ($this->repo->find($id) ?? []) : [], [
                    'name' => $name,
                    'sort_order' => $sort,
                    'active' => $active ? 1 : 0,
                ]),
                'error' => $err,
                'csrf' => Csrf::token(),
            ]);
            return;
        }

        if ($id > 0) {
            $this->repo->update($id, $name, $sort, $active);
        } else {
            $this->repo->create($name, $sort, $active);
        }
        $_SESSION['flash_ok'] = 'Lagerort gespeichert.';
        Response::redirect('/locations');
    }
}
