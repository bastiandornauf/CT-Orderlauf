<?php

declare(strict_types=1);

namespace Tests\Db;

use App\Repositories\ItemRepository;
use App\Repositories\PriceRepository;
use App\Repositories\SupplierArticleRepository;
use App\Services\MaintenancePackageService;
use Tests\DbTestCase;

final class MaintenancePackageTest extends DbTestCase
{
    private function package(int $itemId): array
    {
        return [
            'format' => 'orderlauf-pflegepaket', 'version' => 1, 'title' => 'Pütz RE 4711',
            'source' => ['supplier' => 'Pütz', 'document_ref' => 'RE 4711', 'date' => '2026-10-20'],
            'changes' => [
                ['op' => 'link', 'item_id' => $itemId, 'supplier' => 'Transgourmet', 'priority' => 0],
                ['op' => 'article', 'item_id' => $itemId, 'supplier' => 'Pütz', 'supplier_sku' => '12345', 'pack_size' => '4,25', 'pack_unit' => 'kg'],
                ['op' => 'price', 'item_id' => $itemId, 'item_name' => 'Oliven schwarz', 'price_type' => 'purchase', 'amount' => '24,80', 'note' => 'Pos. 3'],
                ['op' => 'price', 'item_id' => $itemId, 'supplier' => 'Transgourmet', 'price_type' => 'known', 'amount' => '22,10', 'price_date' => '2026-10-24'],
                ['op' => 'alias', 'item_id' => $itemId, 'alias' => 'Oliven'],
            ],
        ];
    }

    private function item(): int
    {
        $id = $this->insertItem('Oliven schwarz', 'Dose');
        (new ItemRepository())->setSupplierLinks($id, [['supplier_id' => 2, 'priority' => 1]]);

        return $id;
    }

    public function testPreviewShowsChangesAndUsesSourceDefaults(): void
    {
        $id = $this->item();
        $rows = (new MaintenancePackageService())->preview($this->package($id));

        self::assertSame(['change', 'change', 'change', 'change', 'change'], array_column($rows, 'status'));
        self::assertSame('Pütz', $rows[2]['supplier']);                 // aus source
        self::assertStringContainsString('RE 4711', $rows[2]['new']);    // Beleg aus source
        // TG-Preis ist erst durch die link-Zeile davor gültig
        self::assertSame('Transgourmet', $rows[3]['supplier']);
    }

    public function testApplyWritesEverythingAndSecondRunChangesNothing(): void
    {
        $id = $this->item();
        $svc = new MaintenancePackageService();
        $pkg = $this->package($id);

        $first = $svc->apply($pkg, [0, 1, 2, 3, 4], null);
        self::assertSame(['applied' => 5, 'skipped' => 0], $first);
        self::assertSame('Oliven', (new ItemRepository())->find($id)['alias']);
        self::assertSame('12345', (new SupplierArticleRepository())->defaultsByItemIds([$id])[$id][2]['supplier_sku']);
        $history = (new PriceRepository())->historyForItem($id);
        self::assertCount(2, $history);
        self::assertSame(['purchase', 'known'], array_column(array_reverse($history), 'price_type'));

        self::assertSame(['same', 'same', 'same', 'same', 'same'], array_column($svc->preview($pkg), 'status'));
        self::assertSame(['applied' => 0, 'skipped' => 5], $svc->apply($pkg, [0, 1, 2, 3, 4], null));
        self::assertCount(2, (new PriceRepository())->historyForItem($id));
    }

    public function testInvalidRowStopsTheWholePackage(): void
    {
        $id = $this->item();
        $pkg = $this->package($id);
        $pkg['changes'][] = ['op' => 'price', 'item_id' => $id, 'supplier' => 'Pütz', 'price_type' => 'purchase', 'amount' => 'abc'];
        $svc = new MaintenancePackageService();

        self::assertSame('error', $svc->preview($pkg)[5]['status']);
        try {
            $svc->apply($pkg, [0, 1, 2, 3, 4, 5], null);
            self::fail('Fehlerhafte Zeile muss das Paket stoppen.');
        } catch (\InvalidArgumentException) {
        }
        self::assertNull((new ItemRepository())->find($id)['alias']);
        self::assertCount(0, (new PriceRepository())->historyForItem($id));
    }

    public function testPriceForUnlinkedSupplierIsAnError(): void
    {
        $id = $this->item();
        $rows = (new MaintenancePackageService())->preview(['format' => 'orderlauf-pflegepaket', 'version' => 1, 'changes' => [
            ['op' => 'price', 'item_id' => $id, 'supplier' => 'OGA', 'price_type' => 'known', 'amount' => '1', 'price_date' => '2026-10-01'],
        ]]);

        self::assertSame('error', $rows[0]['status']);
    }

    public function testWrongItemNameWarns(): void
    {
        $id = $this->item();
        $rows = (new MaintenancePackageService())->preview(['format' => 'orderlauf-pflegepaket', 'version' => 1, 'changes' => [
            ['op' => 'alias', 'item_id' => $id, 'item_name' => 'Kidneybohnen', 'alias' => 'X'],
        ]]);

        self::assertSame('warn', $rows[0]['status']);
    }

    public function testEnvelopeIsValidated(): void
    {
        self::assertNotEmpty(MaintenancePackageService::validateEnvelope(['format' => 'x', 'version' => 1, 'changes' => [[]]]));
        self::assertSame([], MaintenancePackageService::validateEnvelope(['format' => 'orderlauf-pflegepaket', 'version' => 1, 'changes' => [['op' => 'alias']]]));
    }
}
