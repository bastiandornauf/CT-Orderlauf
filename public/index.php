<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Router;

/** @var array<string, array<string, array{0: class-string, 1: string}>> $routes */
$routes = require dirname(__DIR__) . '/config/routes.php';

$router = new Router($routes);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$router->dispatch($method, $uri);
