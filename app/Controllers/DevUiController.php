<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;

final class DevUiController
{
    public function index(): void
    {
        AuthMiddleware::requireAdmin();
        View::layout('layout', 'pages/dev/ui', [
            'title' => 'Komponenten',
            'csrf' => Csrf::token(),
        ]);
    }
}
