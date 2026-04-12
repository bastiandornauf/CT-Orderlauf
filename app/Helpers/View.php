<?php

declare(strict_types=1);

namespace App\Helpers;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $path = APP_ROOT . '/app/Views/' . $template . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException("View not found: {$template}");
        }
        require $path;
    }

    /** @param array<string, mixed> $data */
    public static function layout(string $layout, string $contentTemplate, array $data = []): void
    {
        ob_start();
        self::render($contentTemplate, $data);
        $content = ob_get_clean();
        self::render($layout, array_merge($data, ['content' => $content]));
    }
}
