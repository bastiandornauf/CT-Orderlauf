<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\ItemDisplay;
use App\Helpers\ItemSupplierInput;
use App\Helpers\Units;
use App\Repositories\SupplierArticleRepository;
use PHPUnit\Framework\TestCase;

final class ItemInputTest extends TestCase
{
    public function testDisplayNamePrefersNonEmptyAlias(): void
    {
        self::assertSame('Pommes', ItemDisplay::name(['name' => 'Steakhouse Fries', 'alias' => 'Pommes']));
        self::assertSame('Steakhouse Fries', ItemDisplay::name(['name' => 'Steakhouse Fries', 'alias' => '  ']));
        self::assertSame('Steakhouse Fries', ItemDisplay::name(['name' => 'Steakhouse Fries', 'alias' => null]));
    }

    public function testQuickEditWithoutArticleFieldsLeavesArticlesUntouched(): void
    {
        $parsed = ItemSupplierInput::parse([['supplier_id' => 2, 'priority' => 1]]);

        self::assertSame([['supplier_id' => 2, 'priority' => 1]], $parsed['pairs']);
        self::assertNull($parsed['articles']);
    }

    public function testRowsWithArticleFieldsAreCollectedPerSupplier(): void
    {
        $parsed = ItemSupplierInput::parse([
            ['supplier_id' => 2, 'priority' => 1, 'supplier_label' => 'Oliven 4,25 kg', 'supplier_sku' => '4711'],
            ['supplier_id' => 0, 'priority' => 0, 'supplier_label' => 'ignoriert'],
        ]);

        self::assertSame(['supplier_label' => 'Oliven 4,25 kg', 'supplier_sku' => '4711'], $parsed['articles'][2]);
        self::assertCount(1, $parsed['pairs']);
    }

    public function testPostRowsAreZippedByIndex(): void
    {
        $rows = ItemSupplierInput::rowsFromPost([
            'supplier_id' => ['2', '3'],
            'supplier_priority' => ['1', '0'],
            'supplier_label' => ['A', ''],
            'supplier_sku' => ['', '99'],
        ]);

        self::assertSame('A', $rows[0]['supplier_label']);
        self::assertSame('99', $rows[1]['supplier_sku']);
        self::assertArrayNotHasKey('pack_size', $rows[0]);
    }

    public function testPackIsOnlyStoredWhenSizeAndUnitAreValid(): void
    {
        self::assertSame(['pack_size' => 4.25, 'pack_unit' => 'kg'], self::pack(['pack_size' => '4,25', 'pack_unit' => 'KG']));
        self::assertSame(['pack_size' => null, 'pack_unit' => null], self::pack(['pack_size' => '4,25', 'pack_unit' => '']));
        self::assertSame(['pack_size' => null, 'pack_unit' => null], self::pack(['pack_size' => '0', 'pack_unit' => 'kg']));
        self::assertSame(['pack_size' => null, 'pack_unit' => null], self::pack(['pack_size' => 'viel', 'pack_unit' => 'kg']));
        self::assertSame(['pack_size' => null, 'pack_unit' => null], self::pack(['pack_size' => '3', 'pack_unit' => 'Karton']));
    }

    public function testUnitAliasesFromExistingData(): void
    {
        self::assertSame('kg', Units::normalizePackUnit('Kg'));
        self::assertSame('stk', Units::normalizePackUnit('Stück'));
        self::assertSame('stk', Units::normalizePackUnit('St.'));
        self::assertNull(Units::normalizePackUnit('Ktn'));
    }

    /** @return array{pack_size:?float, pack_unit:?string} */
    private static function pack(array $fields): array
    {
        $c = SupplierArticleRepository::cleanFields($fields);

        return ['pack_size' => $c['pack_size'], 'pack_unit' => $c['pack_unit']];
    }
}
