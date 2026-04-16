<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Controllers\AdminUsersController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ProfileController;
use App\Controllers\ExportController;
use App\Controllers\HelpController;
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
        '/profile' => [ProfileController::class, 'index'],
        '/help' => [HelpController::class, 'index'],
        '/admin/users' => [AdminUsersController::class, 'index'],
        '/admin/users/new' => [AdminUsersController::class, 'form'],
        '/admin/users/edit' => [AdminUsersController::class, 'form'],
        '/import' => [ImportController::class, 'index'],
        '/order/prepare' => [OrderController::class, 'prepare'],
        '/order/round' => [OrderController::class, 'round'],
        '/order/review' => [OrderController::class, 'review'],
        '/order/output' => [OrderController::class, 'output'],
        '/api/order/payload' => [OrderApiController::class, 'payload'],
        '/export/locations' => [ExportController::class, 'locations'],
        '/export/suppliers' => [ExportController::class, 'suppliers'],
        '/export/delivery-days' => [ExportController::class, 'deliveryDays'],
        '/export/items' => [ExportController::class, 'items'],
        '/export/item-supplier' => [ExportController::class, 'itemSupplier'],
    ],
    'POST' => [
        '/login' => [AuthController::class, 'login'],
        '/logout' => [AuthController::class, 'logout'],
        '/locations/save' => [LocationController::class, 'save'],
        '/suppliers/save' => [SupplierController::class, 'save'],
        '/items/save' => [ItemController::class, 'save'],
        '/settings/save' => [SettingsController::class, 'save'],
        '/settings/smtp-test' => [SettingsController::class, 'smtpTest'],
        '/profile/password' => [ProfileController::class, 'password'],
        '/admin/users/save' => [AdminUsersController::class, 'save'],
        '/admin/users/delete' => [AdminUsersController::class, 'delete'],
        '/import/preview' => [ImportController::class, 'preview'],
        '/import/run' => [ImportController::class, 'run'],
        '/api/pdf/supplier' => [OrderApiController::class, 'pdf'],
        '/api/order/send-mail' => [OrderApiController::class, 'sendMail'],
    ],
]);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$router->dispatch($method, $uri);
