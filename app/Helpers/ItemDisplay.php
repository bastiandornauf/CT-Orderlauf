<?php

declare(strict_types=1);

namespace App\Helpers;

final class ItemDisplay
{
    /** Interner Anzeigename: Alias, falls gesetzt, sonst die Standardbezeichnung. */
    public static function name(array $item): string
    {
        $alias = trim((string) ($item['alias'] ?? ''));

        return $alias !== '' ? $alias : (string) ($item['name'] ?? '');
    }
}
