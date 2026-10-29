<?php

declare(strict_types=1);

namespace Tests\Db;

use App\Helpers\Money;
use App\Repositories\ItemRepository;
use App\Repositories\PriceRepository;
use App\Repositories\SupplierArticleRepository;
use App\Services\InventoryValuationService;
use Tests\DbTestCase;

final class InventoryValuationDbTest extends DbTestCase
{
    private function addPrice(int $itemId, int $supplierId, string $amount, string $date, string $type): void
    {
        $articleId = (new SupplierArticleRepository())->ensureDefault($itemId, $supplierId);
        (new PriceRepository())->add([
            'supplier_article_id' => $articleId,
            'price_type' => $type,
            'amount' => Money::parse($amount),
            'per_qty' => Money::SCALE,
            'per_unit' => 'order',
            'price_date' => $date,
            'source' => 'manual',
            'document_ref' => null,
            'note' => null,
            'is_net' => true,
        ], null);
    }

    public function testUsesLatestPurchaseUpToStichtagAcrossSuppliers(): void
    {
        $id = $this->insertItem('Oliven', 'Dose');
        (new ItemRepository())->setSupplierLinks($id, [['supplier_id' => 2, 'priority' => 1], ['supplier_id' => 3, 'priority' => 0]]);
        $this->addPrice($id, 2, '10,00', '2026-09-01', 'purchase');
        $this->addPrice($id, 3, '11,00', '2026-10-01', 'purchase');  // jüngster Einkauf vor Stichtag
        $this->addPrice($id, 2, '9,00', '2026-10-15', 'known');      // Listenpreis zählt nie
        $this->addPrice($id, 2, '12,00', '2026-11-05', 'purchase');  // nach Stichtag

        $items = [['id' => $id, 'unit' => 'Dose', 'base_unit' => null, 'unit_factor' => null]];
        $v = (new InventoryValuationService())->forItems($items, '2026-10-31')[$id];

        self::assertSame('ok', $v['status']);
        self::assertSame('11,00', $v['unit_price']);
        self::assertSame('Transgourmet', $v['supplier']);
        self::assertSame('2026-10-01', $v['price_date']);

        // Spätere Inventur sieht den neueren Einkauf, die frühere bleibt unverändert
        self::assertSame('12,00', (new InventoryValuationService())->forItems($items, '2026-11-30')[$id]['unit_price']);
        self::assertSame('11,00', (new InventoryValuationService())->forItems($items, '2026-10-31')[$id]['unit_price']);
    }

    public function testOnlyKnownPricesMeansNoPurchase(): void
    {
        $id = $this->insertItem('Oliven', 'Dose');
        (new ItemRepository())->setSupplierLinks($id, [['supplier_id' => 2, 'priority' => 1]]);
        $this->addPrice($id, 2, '9,00', '2026-10-15', 'known');

        $v = (new InventoryValuationService())->forItems([['id' => $id, 'unit' => 'Dose']], '2026-10-31')[$id];

        self::assertSame(['status' => 'no_purchase'], $v);
    }
}
