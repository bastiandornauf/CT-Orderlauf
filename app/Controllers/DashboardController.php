<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Database;
use App\Helpers\UserRole;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;

final class DashboardController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        $pdo = Database::pdo();
        View::layout('layout', 'pages/dashboard', [
            'title' => 'Start',
            'csrf' => Csrf::token(),
            'canEditMaster' => UserRole::canEditMasterData((string) ($_SESSION['role'] ?? '')),
            'counts' => [
                'items' => (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn(),
                'suppliers' => (int) $pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn(),
                'locations' => (int) $pdo->query('SELECT COUNT(*) FROM locations')->fetchColumn(),
            ],
        ]);
    }
}
