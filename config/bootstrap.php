<?php

declare(strict_types=1);

$root = dirname(__DIR__);

if (file_exists($root . '/.env')) {
    $lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $_ENV[$k] = $v;
        putenv("$k=$v");
    }
}

foreach (['APP_ENV', 'APP_DEBUG', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'SESSION_NAME'] as $envKey) {
    $v = getenv($envKey);
    if ($v !== false && $v !== '') {
        $_ENV[$envKey] = $v;
    }
}

if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = $root . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

require_once $root . '/config/app.php';
require_once $root . '/config/database.php';

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

session_name(SESSION_NAME);
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (!empty($_SESSION['user_id']) && (!isset($_SESSION['role']) || $_SESSION['role'] === '')) {
    $row = (new \App\Repositories\UserRepository())->findById((int) $_SESSION['user_id']);
    if ($row !== null) {
        $_SESSION['role'] = (string) ($row['role'] ?? \App\Helpers\UserRole::EDITOR);
        $_SESSION['username'] = (string) $row['username'];
    }
}
