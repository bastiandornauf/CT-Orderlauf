<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Response;
use App\Helpers\UserRole;

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

    /** Administrator oder Stammdaten: Lagerorte, Artikel, Import, Einstellungen, Export */
    public static function requireEditor(): void
    {
        self::requireAuth();
        $role = (string) ($_SESSION['role'] ?? '');
        if (!UserRole::canEditMasterData($role)) {
            if (self::isApiRequest()) {
                Response::jsonError('Forbidden', 403);
            }
            Response::redirect('/');
        }
    }

    /** Nur Administrator: Nutzerverwaltung */
    public static function requireAdmin(): void
    {
        self::requireAuth();
        if (!UserRole::isAdmin((string) ($_SESSION['role'] ?? ''))) {
            if (self::isApiRequest()) {
                Response::jsonError('Forbidden', 403);
            }
            Response::redirect('/');
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
