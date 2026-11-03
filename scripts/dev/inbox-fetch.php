<?php
// Lokaler Test: Posteingang einmal abrufen (php scripts/dev/inbox-fetch.php im Web-Container)
declare(strict_types=1);
require dirname(__DIR__, 2) . '/config/bootstrap.php';
$r = (new App\Services\Inbox\InboxService())->fetch(true);
echo json_encode($r + ['counts' => (new App\Repositories\InboxRepository())->counts()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
