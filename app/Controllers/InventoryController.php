<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;

final class InventoryController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        View::layout('layout', 'pages/inventory/index', [
            'title' => 'Inventur',
            'csrf' => Csrf::token(),
        ]);
    }

    /** @deprecated Alias – Weiterleitung auf /inventory */
    public function prepare(): void
    {
        AuthMiddleware::requireAuth();
        Response::redirect('/inventory');
    }

    public function round(): void
    {
        AuthMiddleware::requireAuth();
        View::layout('layout', 'pages/inventory/round', [
            'title' => 'Inventur – Rundgang',
            'csrf' => Csrf::token(),
        ]);
    }

    public function finalize(): void
    {
        AuthMiddleware::requireAuth();
        View::layout('layout', 'pages/inventory/finalize', [
            'title' => 'Inventur – Abschluss',
            'csrf' => Csrf::token(),
        ]);
    }
}
