<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;

final class DashboardController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        View::layout('layout', 'pages/dashboard', [
            'title' => 'Start',
            'csrf' => Csrf::token(),
        ]);
    }
}
