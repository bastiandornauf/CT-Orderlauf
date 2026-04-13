<?php

declare(strict_types=1);

namespace App\Helpers;

final class UserRole
{
    public const ADMIN = 'admin';
    public const EDITOR = 'editor';
    public const ORDER = 'order';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::ADMIN, self::EDITOR, self::ORDER];
    }

    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }

    public static function label(string $role): string
    {
        return match ($role) {
            self::ADMIN => 'Administrator',
            self::EDITOR => 'Stammdaten',
            self::ORDER => 'Nur Bestellen',
            default => $role,
        };
    }

    public static function canEditMasterData(?string $role): bool
    {
        return in_array((string) $role, [self::ADMIN, self::EDITOR], true);
    }

    public static function isAdmin(?string $role): bool
    {
        return $role === self::ADMIN;
    }
}
