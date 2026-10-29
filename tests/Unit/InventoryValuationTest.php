<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Money;
use App\Services\InventoryValuationService as V;
use PHPUnit\Framework\TestCase;

final class InventoryValuationTest extends TestCase
{
    private static function purchase(string $amount, string $perUnit = 'order', array $extra = []): array
    {
        return $extra + [
            'amount' => $amount,
            'per_qty' => '1.0000',
            'per_unit' => $perUnit,
            'price_date' => '2026-10-20',
            'pack_size' => null,
            'pack_unit' => null,
            'order_unit' => null,
            'supplier_name' => 'Pütz',
            'document_ref' => 'RE-1',
        ];
    }

    public function testCountedInOrderUnitUsesPriceDirectly(): void
    {
        $v = V::value(['unit' => 'Karton'], self::purchase('24.8000'));

        self::assertSame(V::OK, $v['status']);
        self::assertSame('direct', $v['method']);
        self::assertSame('24,80', $v['unit_price']);
        self::assertSame('Pütz', $v['supplier']);
        self::assertSame('24,80 €/Karton', $v['basis']);
    }

    public function testExampleFromSpecTenKiloCartonCountedInKilo(): void
    {
        // Letzter Einkauf 10 kg für 24,80 €, gezählt wird in kg → 2,48 €/kg; 3,5 kg = 8,68 €
        $v = V::value(['unit' => 'kg'], self::purchase('24.8000', 'order', ['pack_size' => '10.0000', 'pack_unit' => 'kg', 'order_unit' => 'Karton']));

        self::assertSame(V::OK, $v['status']);
        self::assertSame('2,48', $v['unit_price']);
        self::assertSame(86800, Money::mulDiv(35000, $v['unit_price_units'], Money::SCALE));
    }

    public function testExplicitUnitContentConvertsPerKiloPrice(): void
    {
        // Preis je kg, gezählt wird in Beuteln à 2,5 kg
        $item = ['unit' => 'Beutel', 'unit_factor' => '2.5000', 'base_unit' => 'kg'];
        $v = V::value($item, self::purchase('3.2000', 'kg'));

        self::assertSame('8,00', $v['unit_price']);
        self::assertSame('converted', $v['method']);
    }

    public function testGramsCountedAgainstKiloPrice(): void
    {
        $v = V::value(['unit' => 'g'], self::purchase('12.0000', 'kg'));

        self::assertSame(V::OK, $v['status']);
        self::assertSame(120, $v['unit_price_units']); // 0,012 €/g
    }

    public function testDifferentOrderUnitWithoutContentIsNotValued(): void
    {
        // Gekauft je Karton (Gebinde unbekannt), gezählt in Stück → keine geratene Umrechnung
        $v = V::value(['unit' => 'Stück'], self::purchase('24.8000', 'order', ['order_unit' => 'Karton']));

        self::assertSame(V::NOT_CONVERTIBLE, $v['status']);
        self::assertArrayNotHasKey('unit_price', $v);
        self::assertSame('Pütz', $v['supplier']);
    }

    public function testIncompatibleBaseIsNotValued(): void
    {
        $item = ['unit' => 'Flasche', 'unit_factor' => '0.7000', 'base_unit' => 'l'];
        $v = V::value($item, self::purchase('9.0000', 'kg'));

        self::assertSame(V::NOT_CONVERTIBLE, $v['status']);
    }
}
