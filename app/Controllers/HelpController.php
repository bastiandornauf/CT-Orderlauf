<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\UserManualHtml;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;

final class HelpController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        $path = APP_ROOT . '/docs/ANLEITUNG.md';
        $html = UserManualHtml::renderFile($path);
        View::layout('layout', 'pages/help', [
            'title' => 'Hilfe',
            'csrf' => Csrf::token(),
            'helpHtml' => $html ?? '',
            'manualMissing' => $html === null,
        ]);
    }
}
