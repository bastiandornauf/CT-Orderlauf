<?php

declare(strict_types=1);

/**
 * Zentrale Routen-Definition (wird von public/index.php geladen).
 * Bei Deploy: diese Datei UND public/index.php müssen auf dem Server liegen.
 */
use App\Controllers\AdminUsersController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ExportController;
use App\Controllers\HelpController;
use App\Controllers\ImportController;
use App\Controllers\InventoryApiController;
use App\Controllers\InventoryController;
use App\Controllers\ItemApiController;
use App\Controllers\ItemController;
use App\Controllers\LocationController;
use App\Controllers\OrderApiController;
use App\Controllers\OrderController;
use App\Controllers\ProfileController;
use App\Controllers\SettingsController;
use App\Controllers\SupplierController;

return [
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
        '/items/pending' => [ItemController::class, 'pending'],
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
        '/inventory' => [InventoryController::class, 'index'],
        '/inventory/prepare' => [InventoryController::class, 'prepare'],
        '/inventory/round' => [InventoryController::class, 'round'],
        '/inventory/finalize' => [InventoryController::class, 'finalize'],
        '/api/order/payload' => [OrderApiController::class, 'payload'],
        '/api/inventory/payload' => [InventoryApiController::class, 'payload'],
        '/export/locations' => [ExportController::class, 'locations'],
        '/export/suppliers' => [ExportController::class, 'suppliers'],
        '/export/delivery-days' => [ExportController::class, 'deliveryDays'],
        '/export/items' => [ExportController::class, 'items'],
        '/export/item-prices' => [ExportController::class, 'itemPrices'],
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
        '/profile/save' => [ProfileController::class, 'save'],
        '/profile/password' => [ProfileController::class, 'password'],
        '/admin/users/save' => [AdminUsersController::class, 'save'],
        '/admin/users/delete' => [AdminUsersController::class, 'delete'],
        '/import/preview' => [ImportController::class, 'preview'],
        '/import/run' => [ImportController::class, 'run'],
        '/api/pdf/supplier' => [OrderApiController::class, 'pdf'],
        '/api/order/send-mail' => [OrderApiController::class, 'sendMail'],
        '/api/items/save' => [ItemApiController::class, 'save'],
        '/api/items/create' => [ItemApiController::class, 'create'],
    ],
];
