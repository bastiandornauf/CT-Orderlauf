<?php

declare(strict_types=1);

// Nur Autoload – keine Session, keine DB-Verbindung. DB-Tests richten ihre
// eigene Test-Datenbank ein und fassen die eingespielte Prod-Kopie nicht an.
require dirname(__DIR__) . '/vendor/autoload.php';
