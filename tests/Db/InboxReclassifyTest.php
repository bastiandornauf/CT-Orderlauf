<?php

declare(strict_types=1);

namespace Tests\Db;

use App\Repositories\InboxRepository;
use App\Repositories\SettingsRepository;
use App\Services\Inbox\InboxService;
use Tests\DbTestCase;

final class InboxReclassifyTest extends DbTestCase
{
    public function testSavingOwnAddressesFixesMailsAlreadyAssignedToPlaceholderSupplier(): void
    {
        // Transgourmet (Webshop) hat die eigene Adresse als Platzhalter
        $this->pdo->exec("UPDATE suppliers SET email = 'gastro@haus.de' WHERE id = 3");
        $this->pdo->exec("UPDATE suppliers SET email = 'info@puetz.de' WHERE id = 2");
        $settings = new SettingsRepository();
        $settings->set('inbox_own_addresses', null);
        $repo = new InboxRepository();
        $row = static fn (int $uid, string $from, string $subject, string $kind, ?int $sup): array => [
            'imap_uid' => $uid, 'imap_uidvalidity' => 1, 'message_id' => "<{$uid}@x>", 'received_at' => '2026-11-03 10:00:00',
            'from_email' => $from, 'from_name' => '', 'subject' => $subject, 'body_text' => '',
            'kind' => $kind, 'supplier_id' => $sup, 'status' => 'neu',
        ];
        // So wurden sie ohne eingetragene eigene Adressen abgeholt
        $reply = $repo->insert($row(1, 'gastro@haus.de', 'AW: Bestellung', 'lieferant', 3));
        $receipt = $repo->insert($row(2, 'gastro@haus.de', 'WG: Rechnung', 'lieferant', 3));
        $colleague = $repo->insert($row(3, 'kollegin@haus.de', 'Frage', 'lieferant', 3));
        $puetz = $repo->insert($row(4, 'info@puetz.de', 'AW: Bestellung', 'lieferant', 2));

        $settings->set('inbox_own_addresses', 'gastro@haus.de');
        $changed = (new InboxService($settings, $repo))->reclassify();

        self::assertSame(3, $changed);
        self::assertSame(['nachtrag', 'erledigt', null], [$repo->find($reply)['kind'], $repo->find($reply)['status'], $repo->find($reply)['supplier_id']]);
        self::assertSame(['beleg', 'neu'], [$repo->find($receipt)['kind'], $repo->find($receipt)['status']]);
        self::assertSame('sonstiges', $repo->find($colleague)['kind']);
        self::assertSame(['lieferant', 2], [$repo->find($puetz)['kind'], $repo->find($puetz)['supplier_id']]);
        self::assertSame(0, (new InboxService($settings, $repo))->reclassify());
    }
}
