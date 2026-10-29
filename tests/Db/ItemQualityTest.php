<?php

declare(strict_types=1);

namespace Tests\Db;

use App\Helpers\Money;
use App\Repositories\ItemRepository;
use App\Repositories\PriceRepository;
use App\Repositories\SupplierArticleRepository;
use App\Services\ItemQualityService;
use Tests\DbTestCase;

final class ItemQualityTest extends DbTestCase
{
    private function item(string $name, string $unit, array $suppliers): int
    {
        $id = $this->insertItem($name, $unit);
        (new ItemRepository())->setSupplierLinks($id, array_map(static fn (int $s): array => ['supplier_id' => $s, 'priority' => 1], $suppliers));

        return $id;
    }

    private function price(int $itemId, int $supplierId, string $amount, string $date): void
    {
        (new PriceRepository())->add([
            'supplier_article_id' => (new SupplierArticleRepository())->ensureDefault($itemId, $supplierId),
            'price_type' => 'known', 'amount' => Money::parse($amount), 'per_qty' => Money::SCALE, 'per_unit' => 'order',
            'price_date' => $date, 'source' => 'manual', 'document_ref' => null, 'note' => null, 'is_net' => true,
        ], null);
    }

    public function testRareUnitSpellingIsFlaggedAgainstTheCommonOne(): void
    {
        $this->item('A', 'Karton', [2]);
        $this->item('B', 'Karton', [2]);
        $c = $this->item('C', 'Ktn', [2]);

        self::assertSame([$c => '„Ktn“ → „Karton“'], (new ItemQualityService())->run('unit_spelling'));
    }

    public function testSameSkuOnTwoItemsIsAnError(): void
    {
        $a = $this->item('Honig A', 'Flasche', [3]);
        $b = $this->item('Honig B', 'Flasche', [3]);
        $repo = new SupplierArticleRepository();
        $repo->saveDefaults($a, [3 => ['supplier_sku' => '447621']]);
        $repo->saveDefaults($b, [3 => ['supplier_sku' => '447621']]);

        self::assertSame([$a, $b], array_keys((new ItemQualityService())->run('duplicate_sku')));
    }

    public function testArticleWithoutLinkIsOrphan(): void
    {
        $a = $this->item('Oliven', 'Dose', [2, 3]);
        (new SupplierArticleRepository())->saveDefaults($a, [3 => ['supplier_sku' => '1']]);
        (new ItemRepository())->setSupplierLinks($a, [['supplier_id' => 2, 'priority' => 1]]);

        self::assertSame([$a => 'Transgourmet'], (new ItemQualityService())->run('orphan_article'));
    }

    public function testMissingSkuOnlyForSuppliersWithSkus(): void
    {
        $this->pdo->exec("UPDATE suppliers SET has_skus = 1 WHERE id = 2");
        $a = $this->item('Oliven', 'Dose', [1, 2]);   // OGA ohne Art.-Nr., Pütz mit

        self::assertSame([$a => 'Pütz'], (new ItemQualityService())->run('missing_sku'));
    }

    public function testFactorTenJumpIsFlagged(): void
    {
        $a = $this->item('Pinienkerne', 'Beutel', [2]);
        $this->price($a, 2, '5,30', '2026-09-01');
        $this->price($a, 2, '53,00', '2026-10-01');
        $b = $this->item('Zucker', 'kg', [2]);
        $this->price($b, 2, '1,10', '2026-09-01');
        $this->price($b, 2, '1,30', '2026-10-01');

        self::assertSame([$a => 'Pütz: 5,30 € → 53,00 €'], (new ItemQualityService())->run('price_jump'));
    }

    public function testStalePriceAfterSixMonths(): void
    {
        $old = $this->item('Alt', 'kg', [2]);
        $this->price($old, 2, '1,00', (new \DateTimeImmutable('-7 months'))->format('Y-m-d'));
        $new = $this->item('Neu', 'kg', [2]);
        $this->price($new, 2, '1,00', (new \DateTimeImmutable('-1 month'))->format('Y-m-d'));

        self::assertSame([$old], array_keys((new ItemQualityService())->run('stale_price')));
    }

    public function testCountsCoverAllChecks(): void
    {
        self::assertSame(array_keys(ItemQualityService::CHECKS), array_keys((new ItemQualityService())->counts()));
    }
}
