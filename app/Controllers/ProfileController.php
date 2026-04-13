<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Repositories\UserRepository;

final class ProfileController
{
    public function __construct(
        private UserRepository $users = new UserRepository()
    ) {
    }

    public function index(): void
    {
        AuthMiddleware::requireAuth();
        $id = (int) ($_SESSION['user_id'] ?? 0);
        $user = $this->users->findById($id);
        if ($user === null) {
            Response::redirect('/login');
            return;
        }
        View::layout('layout', 'pages/profile', [
            'title' => 'Mein Konto',
            'username' => $user['username'],
            'csrf' => Csrf::token(),
        ]);
    }

    public function password(): void
    {
        AuthMiddleware::requireAuth();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/profile');
            return;
        }
        $id = (int) ($_SESSION['user_id'] ?? 0);
        $current = (string) ($_POST['current_password'] ?? '');
        $pass = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password_confirm'] ?? '');

        $row = $this->users->findByUsername((string) ($_SESSION['username'] ?? ''));
        if ($row === null || !password_verify($current, $row['password_hash'])) {
            View::layout('layout', 'pages/profile', [
                'title' => 'Mein Konto',
                'username' => (string) ($_SESSION['username'] ?? ''),
                'error' => 'Aktuelles Passwort ist falsch.',
                'csrf' => Csrf::token(),
            ]);
            return;
        }
        if (strlen($pass) < 8) {
            View::layout('layout', 'pages/profile', [
                'title' => 'Mein Konto',
                'username' => (string) ($_SESSION['username'] ?? ''),
                'error' => 'Neues Passwort mindestens 8 Zeichen.',
                'csrf' => Csrf::token(),
            ]);
            return;
        }
        if ($pass !== $pass2) {
            View::layout('layout', 'pages/profile', [
                'title' => 'Mein Konto',
                'username' => (string) ($_SESSION['username'] ?? ''),
                'error' => 'Die neuen Passwörter stimmen nicht überein.',
                'csrf' => Csrf::token(),
            ]);
            return;
        }

        $this->users->updatePasswordHash($id, password_hash($pass, PASSWORD_DEFAULT));
        $_SESSION['flash_ok'] = 'Passwort geändert.';
        Response::redirect('/profile?saved=1');
    }
}
