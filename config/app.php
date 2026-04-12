<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? '0', FILTER_VALIDATE_BOOLEAN));
define('SESSION_NAME', $_ENV['SESSION_NAME'] ?? 'ct_orderlauf_session');
