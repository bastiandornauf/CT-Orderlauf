<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Repositories\SettingsRepository;

final class OrderController
{
    public function prepare(): void
    {
        AuthMiddleware::requireAuth();
        Response::redirect('/?open=bestellen');
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
        $settings = new SettingsRepository();
        View::layout('layout', 'pages/order/output', [
            'title' => 'Ausgabe',
            'csrf' => Csrf::token(),
            'output_show_outlook' => $settings->get('ui_show_outlook_export', '1') === '1',
            'output_show_pdf' => $settings->get('ui_show_pdf', '1') === '1',
        ]);
    }
}
