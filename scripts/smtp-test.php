#!/usr/bin/env php
<?php

/**
 * CLI: SMTP nur Verbindung + Auth (wie Einstellungen → „SMTP-Verbindung testen“).
 * Nutzt dieselbe Datenbank wie die Web-App (.env).
 *
 *   php scripts/smtp-test.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$r = (new App\Services\MailSenderService())->testSmtpConnection();

if ($r['ok']) {
    fwrite(STDOUT, ($r['detail'] ?? 'OK') . PHP_EOL);
    exit(0);
}

fwrite(STDERR, ($r['error'] ?? 'Fehler') . PHP_EOL);
exit(1);
