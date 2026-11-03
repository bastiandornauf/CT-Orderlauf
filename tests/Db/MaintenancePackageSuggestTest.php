<?php

declare(strict_types=1);

namespace Tests\Db;

use App\Services\MaintenancePackageService;
use Tests\DbTestCase;

final class MaintenancePackageSuggestTest extends DbTestCase
{
    private function pkg(array $changes): array
    {
        return ['format' => 'orderlauf-pflegepaket', 'version' => 1, 'title' => 'TG LS', 'source' => ['supplier' => 'Transgourmet'], 'changes' => $changes];
    }

    public function testSuggestAddsPendingItemOnceAndRefusesExistingItems(): void
    {
        $this->pdo->exec('DELETE FROM pending_items');
        $this->insertItem('Kürbis, Hokkaido', 'Stück');
        $pkg = $this->pkg([
            ['op' => 'suggest', 'name' => 'Kabeljau Loin MSC (TK)', 'unit' => 'Karton (5 kg)', 'location' => 'TL', 'quantity' => '1'],
            ['op' => 'suggest', 'name' => 'kürbis, hokkaido', 'unit' => 'Beutel'],
            ['op' => 'suggest', 'name' => 'Ponzu', 'location' => 'Gibtsnicht'],
        ]);
        $svc = new MaintenancePackageService();

        $rows = $svc->preview($pkg);
        self::assertSame(['change', 'error', 'error'], array_column($rows, 'status'));
        self::assertSame('Transgourmet', $rows[0]['supplier']);
        self::assertStringContainsString('gibt es schon', $rows[1]['message']);
        self::assertStringContainsString('Lagerort', $rows[2]['message']);

        $pkg['changes'] = [$pkg['changes'][0]];
        self::assertSame(1, $svc->apply($pkg, [0], null)['applied']);
        $p = $this->pdo->query("SELECT name, unit, last_quantity, supplier_id, location_id, status FROM pending_items")->fetch(\PDO::FETCH_ASSOC);
        self::assertSame(['name' => 'Kabeljau Loin MSC (TK)', 'unit' => 'Karton (5 kg)', 'last_quantity' => '1', 'supplier_id' => 3, 'location_id' => 1, 'status' => 'open'], $p);

        self::assertSame('same', $svc->preview($pkg)[0]['status']);
        self::assertSame(['applied' => 0, 'skipped' => 1, 'receipts_done' => 0], $svc->apply($pkg, [0], null));
    }

    public function testLinksFreelyOrderedSuggestionWithReceiptData(): void
    {
        $this->pdo->exec('DELETE FROM pending_items');
        $repo = new \App\Repositories\PendingItemRepository();
        $repo->record(['name' => '5kg TK Kabeljau', 'unit' => 'kg', 'quantity' => '5', 'location_id' => 1], null);
        $pendingId = (int) $repo->idByName('5kg TK Kabeljau');
        $change = [
            'op' => 'suggest', 'pending_id' => $pendingId,
            'article' => ['supplier_label' => 'MSC Paz.Kab.Loin180-200g TK5k', 'supplier_sku' => '657372'],
            'price' => ['amount' => '21,096', 'per_unit' => 'kg', 'price_type' => 'purchase', 'source' => 'invoice'],
        ];
        $pkg = $this->pkg([$change]);
        $pkg['source'] += ['document_ref' => 'LS 2207189445', 'date' => '2026-11-03'];
        $svc = new MaintenancePackageService();

        $row = $svc->preview($pkg)[0];
        self::assertSame('warn', $row['status']);
        self::assertStringContainsString('5kg TK Kabeljau', $row['item']);
        self::assertSame('MSC Paz.Kab.Loin180-200g TK5k · Art.-Nr. 657372 · 21,096 €/kg · LS 2207189445', $row['new']);

        self::assertSame(1, $svc->apply($pkg, [0], null)['applied']);
        $p = $repo->find($pendingId);
        $receipt = json_decode($p['receipt'], true);
        self::assertSame(3, (int) $p['supplier_id']);   // Lieferant aus Beleg ergänzt
        self::assertSame('657372', $receipt['supplier_sku']);
        self::assertSame(['price_type' => 'purchase', 'amount' => '21,0960', 'per_qty' => '1,0000', 'per_unit' => 'kg', 'source' => 'invoice',
            'price_date' => '2026-11-03', 'document_ref' => 'LS 2207189445', 'note' => null, 'is_net' => true], $receipt['price']);
        self::assertSame('same', $svc->preview($pkg)[0]['status']);

        // neuer Vorschlag mit Beleg-Daten in einem Schritt
        $pkg2 = $this->pkg([['op' => 'suggest', 'name' => 'Ponzu Sauce vegan', 'unit' => 'Flasche', 'article' => ['supplier_sku' => '447612'],
            'price' => ['amount' => '11,84', 'price_type' => 'purchase', 'price_date' => '2026-11-03']]]);
        $svc->apply($pkg2, [0], null);
        self::assertSame('447612', json_decode($repo->find((int) $repo->idByName('Ponzu Sauce vegan'))['receipt'], true)['supplier_sku']);
    }
}
