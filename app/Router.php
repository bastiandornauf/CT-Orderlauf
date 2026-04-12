<?php

declare(strict_types=1);

namespace App;

use App\Helpers\Response;

final class Router
{
    /** @param array<string, array<string, array{0: class-string, 1: string}>> $routes */
    public function __construct(
        private array $routes
    ) {
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        if ($path === '') {
            $path = '/';
        }

        $handler = $this->routes[$method][$path] ?? null;
        if ($handler === null) {
            http_response_code(404);
            if (str_starts_with($path, '/api/')) {
                Response::jsonError('Not found', 404);
            }
            echo '404 Not Found';
            exit;
        }

        [$class, $action] = $handler;
        $controller = new $class();
        $controller->$action();
    }
}
