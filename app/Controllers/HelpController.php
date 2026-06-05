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
        $html = null;
        foreach ([
            APP_ROOT . '/app/Data/ANLEITUNG.md',
            APP_ROOT . '/docs/ANLEITUNG.md',
        ] as $path) {
            $html = UserManualHtml::renderFile($path);
            if ($html !== null) {
                break;
            }
        }
        View::layout('layout', 'pages/help', [
            'title' => 'Hilfe',
            'csrf' => Csrf::token(),
            'helpHtml' => $html ?? '',
            'manualMissing' => $html === null,
        ]);
    }
}
