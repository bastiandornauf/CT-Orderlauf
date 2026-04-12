<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ImportController;
use App\Controllers\ItemController;
use App\Controllers\LocationController;
use App\Controllers\OrderApiController;
use App\Controllers\OrderController;
use App\Controllers\SettingsController;
use App\Controllers\SupplierController;
use App\Router;

$router = new Router([
    'GET' => [
        '/' => [DashboardController::class, 'index'],
        '/login' => [AuthController::class, 'showLogin'],
        '/locations' => [LocationController::class, 'index'],
        '/locations/new' => [LocationController::class, 'form'],
        '/locations/edit' => [LocationController::class, 'form'],
        '/suppliers' => [SupplierController::class, 'index'],
        '/suppliers/new' => [SupplierController::class, 'form'],
        '/suppliers/edit' => [SupplierController::class, 'form'],
        '/items' => [ItemController::class, 'index'],
        '/items/new' => [ItemController::class, 'form'],
        '/items/edit' => [ItemController::class, 'form'],
        '/settings' => [SettingsController::class, 'index'],
        '/import' => [ImportController::class, 'index'],
        '/order/prepare' => [OrderController::class, 'prepare'],
        '/order/round' => [OrderController::class, 'round'],
        '/order/review' => [OrderController::class, 'review'],
        '/order/output' => [OrderController::class, 'output'],
        '/api/order/payload' => [OrderApiController::class, 'payload'],
    ],
    'POST' => [
        '/login' => [AuthController::class, 'login'],
        '/logout' => [AuthController::class, 'logout'],
        '/locations/save' => [LocationController::class, 'save'],
        '/suppliers/save' => [SupplierController::class, 'save'],
        '/items/save' => [ItemController::class, 'save'],
        '/settings/save' => [SettingsController::class, 'save'],
        '/import/preview' => [ImportController::class, 'preview'],
        '/import/run' => [ImportController::class, 'run'],
        '/api/pdf/supplier' => [OrderApiController::class, 'pdf'],
    ],
]);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$router->dispatch($method, $uri);
