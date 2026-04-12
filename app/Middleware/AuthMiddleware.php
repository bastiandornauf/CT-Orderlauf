<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Response;

final class AuthMiddleware
{
    public static function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            if (self::isApiRequest()) {
                Response::jsonError('Unauthorized', 401);
            }
            Response::redirect('/login');
        }
    }

    public static function guestOnly(): void
    {
        if (!empty($_SESSION['user_id'])) {
            Response::redirect('/');
        }
    }

    private static function isApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return str_starts_with(parse_url($uri, PHP_URL_PATH) ?: '', '/api/');
    }
}
