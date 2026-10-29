<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Services\ItemQualityService;

/** Datenpflege: Übersicht über Datenqualitätsprüfungen des Artikelbestands */
final class MaintenanceController
{
    public function index(): void
    {
        AuthMiddleware::requireEditor();
        $counts = (new ItemQualityService())->counts();
        $groups = [];
        foreach (ItemQualityService::CHECKS as $key => [$title, $severity, $group, $help]) {
            $groups[$group][] = ['key' => $key, 'title' => $title, 'severity' => $severity, 'help' => $help, 'count' => $counts[$key]];
        }

        View::layout('layout', 'pages/maintenance/index', [
            'title' => 'Datenpflege',
            'groups' => $groups,
            'errors' => array_sum(array_map(
                static fn (string $k): int => ItemQualityService::CHECKS[$k][1] === 'error' ? $counts[$k] : 0,
                array_keys($counts)
            )),
        ]);
    }
}
