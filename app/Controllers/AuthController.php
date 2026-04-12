<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;

final class AuthController
{
    public function showLogin(): void
    {
        AuthMiddleware::guestOnly();
        View::layout('layout', 'pages/login', [
            'title' => 'Anmelden',
            'csrf' => Csrf::token(),
        ]);
    }

    public function login(): void
    {
        AuthMiddleware::guestOnly();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            View::layout('layout', 'pages/login', [
                'title' => 'Anmelden',
                'csrf' => Csrf::token(),
                'error' => 'Ungültige Anfrage.',
            ]);
            return;
        }
        $user = trim((string) ($_POST['username'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');
        $auth = new AuthService();
        if (!$auth->attempt($user, $pass)) {
            View::layout('layout', 'pages/login', [
                'title' => 'Anmelden',
                'csrf' => Csrf::token(),
                'error' => 'Login fehlgeschlagen.',
            ]);
            return;
        }
        Response::redirect('/');
    }

    public function logout(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/');
            return;
        }
        (new AuthService())->logout();
        Response::redirect('/login');
    }
}
