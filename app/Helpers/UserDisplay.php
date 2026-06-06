<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Anzeigename für Bestell-Mails (Platzhalter {{USER}}).
 */
final class UserDisplay
{
    /**
     * @param array<string, mixed>|null $user Zeile aus users (display_name, username)
     */
    public static function mailName(?array $user): string
    {
        if ($user === null) {
            return '';
        }
        $display = trim((string) ($user['display_name'] ?? ''));
        if ($display !== '') {
            return $display;
        }

        return trim((string) ($user['username'] ?? ''));
    }
}
