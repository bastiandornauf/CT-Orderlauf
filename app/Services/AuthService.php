<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(
        private UserRepository $users = new UserRepository()
    ) {
    }

    public function attempt(string $username, string $password): bool
    {
        $user = $this->users->findByUsername($username);
        if ($user === null) {
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
