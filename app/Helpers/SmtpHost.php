<?php

declare(strict_types=1);

namespace App\Helpers;

final class SmtpHost
{
    /**
     * smtp.ionos.de: Relay verlangt u. a., dass Absender = SMTP-Anmeldung (vollständige E-Mail).
     */
    public static function requiresSenderEqualsSmtpUser(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        return str_contains(strtolower($host), 'ionos');
    }
}
