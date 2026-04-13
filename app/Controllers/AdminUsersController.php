<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\UserRole;
use App\Helpers\Validator;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Repositories\UserRepository;

final class AdminUsersController
{
    public function __construct(
        private UserRepository $users = new UserRepository()
    ) {
    }

    public function index(): void
    {
        AuthMiddleware::requireAdmin();
        View::layout('layout', 'pages/admin/users-index', [
            'title' => 'Benutzer',
            'users' => $this->users->allOrdered(),
            'csrf' => Csrf::token(),
            'currentUserId' => (int) ($_SESSION['user_id'] ?? 0),
        ]);
    }

    public function form(): void
    {
        AuthMiddleware::requireAdmin();
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        if (str_contains($path, '/admin/users/edit') && ($id === null || $id <= 0)) {
            Response::redirect('/admin/users');
            return;
        }
        $row = null;
        if ($id) {
            $row = $this->users->findById($id);
            if ($row === null) {
                Response::redirect('/admin/users');
                return;
            }
        }
        View::layout('layout', 'pages/admin/users-form', [
            'title' => $row ? 'Benutzer bearbeiten' : 'Benutzer anlegen',
            'user' => $row,
            'csrf' => Csrf::token(),
            'roleLabels' => [
                UserRole::ADMIN => UserRole::label(UserRole::ADMIN),
                UserRole::EDITOR => UserRole::label(UserRole::EDITOR),
                UserRole::ORDER => UserRole::label(UserRole::ORDER),
            ],
        ]);
    }

    public function save(): void
    {
        AuthMiddleware::requireAdmin();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/admin/users');
            return;
        }
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password_confirm'] ?? '');

        if (!UserRole::isValid($role)) {
            $this->formError($id, $username, $email, $role, 'Ungültige Rolle.');
            return;
        }

        $err = $email !== '' ? Validator::email($email) : null;
        if ($err) {
            $this->formError($id, $username, $email, $role, $err);
            return;
        }

        if ($id <= 0) {
            $err = $this->validateUsername($username);
            if ($err) {
                $this->formError(0, $username, $email, $role, $err);
                return;
            }
            if ($this->users->usernameExists($username)) {
                $this->formError(0, $username, $email, $role, 'Dieser Benutzername ist bereits vergeben.');
                return;
            }
            if (strlen($pass) < 8) {
                $this->formError(0, $username, $email, $role, 'Passwort mindestens 8 Zeichen.');
                return;
            }
            if ($pass !== $pass2) {
                $this->formError(0, $username, $email, $role, 'Passwörter stimmen nicht überein.');
                return;
            }
            $this->users->create(
                $username,
                $email === '' ? null : $email,
                $role,
                password_hash($pass, PASSWORD_DEFAULT)
            );
            $_SESSION['flash_ok'] = 'Benutzer angelegt.';
            Response::redirect('/admin/users');
            return;
        }

        $existing = $this->users->findById($id);
        if ($existing === null) {
            Response::redirect('/admin/users');
            return;
        }

        if ($existing['role'] === UserRole::ADMIN && $role !== UserRole::ADMIN
            && $this->users->countAdmins() === 1) {
            $this->formError($id, $existing['username'], $email, $role, 'Der letzte Administrator kann nicht herabgestuft werden.');
            return;
        }

        if ($pass !== '' || $pass2 !== '') {
            if (strlen($pass) < 8) {
                $this->formError($id, $existing['username'], $email, $role, 'Passwort mindestens 8 Zeichen.');
                return;
            }
            if ($pass !== $pass2) {
                $this->formError($id, $existing['username'], $email, $role, 'Passwörter stimmen nicht überein.');
                return;
            }
            $hash = password_hash($pass, PASSWORD_DEFAULT);
        } else {
            $hash = null;
        }

        $this->users->update(
            $id,
            $email === '' ? null : $email,
            $role,
            $hash
        );

        if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
            $_SESSION['role'] = $role;
        }

        $_SESSION['flash_ok'] = 'Benutzer gespeichert.';
        Response::redirect('/admin/users');
    }

    public function delete(): void
    {
        AuthMiddleware::requireAdmin();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/admin/users');
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::redirect('/admin/users');
            return;
        }
        if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
            $_SESSION['flash_err'] = 'Sie können sich nicht selbst löschen.';
            Response::redirect('/admin/users');
            return;
        }
        $target = $this->users->findById($id);
        if ($target === null) {
            Response::redirect('/admin/users');
            return;
        }
        if ($target['role'] === UserRole::ADMIN && $this->users->countAdmins() === 1) {
            $_SESSION['flash_err'] = 'Der letzte Administrator kann nicht gelöscht werden.';
            Response::redirect('/admin/users');
            return;
        }
        $this->users->delete($id);
        $_SESSION['flash_ok'] = 'Benutzer gelöscht.';
        Response::redirect('/admin/users');
    }

    private function validateUsername(string $username): ?string
    {
        if (strlen($username) < 2) {
            return 'Benutzername mindestens 2 Zeichen.';
        }
        if (strlen($username) > 64) {
            return 'Benutzername zu lang.';
        }
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
            return 'Benutzername: nur Buchstaben, Ziffern, Punkt, Unterstrich, Bindestrich.';
        }
        return null;
    }

    /** @param array<string, string>|null $userRow */
    private function formError(int $id, string $username, string $email, string $role, string $message): void
    {
        $row = $id > 0 ? $this->users->findById($id) : null;
        View::layout('layout', 'pages/admin/users-form', [
            'title' => $row ? 'Benutzer bearbeiten' : 'Benutzer anlegen',
            'user' => $row
                ? array_merge($row, ['email' => $email, 'role' => $role])
                : ['username' => $username, 'email' => $email, 'role' => $role],
            'error' => $message,
            'csrf' => Csrf::token(),
            'roleLabels' => [
                UserRole::ADMIN => UserRole::label(UserRole::ADMIN),
                UserRole::EDITOR => UserRole::label(UserRole::EDITOR),
                UserRole::ORDER => UserRole::label(UserRole::ORDER),
            ],
        ]);
    }
}
