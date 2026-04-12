<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;

final class OrderController
{
    public function prepare(): void
    {
        AuthMiddleware::requireAuth();
        View::layout('layout', 'pages/order/prepare', [
            'title' => 'Bestellung vorbereiten',
            'csrf' => Csrf::token(),
        ]);
    }

    public function round(): void
    {
        AuthMiddleware::requireAuth();
        View::layout('layout', 'pages/order/round', [
            'title' => 'Rundgang',
            'csrf' => Csrf::token(),
        ]);
    }

    public function review(): void
    {
        AuthMiddleware::requireAuth();
        View::layout('layout', 'pages/order/review', [
            'title' => 'Kontrolle',
            'csrf' => Csrf::token(),
        ]);
    }

    public function output(): void
    {
        AuthMiddleware::requireAuth();
        View::layout('layout', 'pages/order/output', [
            'title' => 'Ausgabe',
            'csrf' => Csrf::token(),
        ]);
    }
}
