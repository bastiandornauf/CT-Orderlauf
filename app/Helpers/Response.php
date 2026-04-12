<?php

declare(strict_types=1);

namespace App\Helpers;

final class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function jsonError(string $message, int $status = 400, array $extra = []): never
    {
        self::json(array_merge(['ok' => false, 'error' => $message], $extra), $status);
    }

    public static function jsonOk(array $data = []): never
    {
        self::json(array_merge(['ok' => true], $data));
    }

    public static function redirect(string $path, int $code = 302): never
    {
        header('Location: ' . $path, true, $code);
        exit;
    }
}
