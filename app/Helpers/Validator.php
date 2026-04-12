<?php

declare(strict_types=1);

namespace App\Helpers;

final class Validator
{
    /** @param array<string, mixed> $data */
    public static function required(array $data, string $key): ?string
    {
        if (!isset($data[$key]) || (is_string($data[$key]) && trim($data[$key]) === '')) {
            return "Feld „{$key}“ ist erforderlich.";
        }
        return null;
    }

    public static function email(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'Ungültige E-Mail-Adresse.';
        }
        return null;
    }

    public static function intPositive(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value) || (int) $value < 0) {
            return 'Ungültige Zahl.';
        }
        return null;
    }
}
