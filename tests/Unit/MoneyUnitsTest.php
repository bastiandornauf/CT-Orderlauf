<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Money;
use App\Helpers\Units;
use PHPUnit\Framework\TestCase;

final class MoneyUnitsTest extends TestCase
{
    public function testParsesGermanAndDotNotation(): void
    {
        self::assertSame(248000, Money::parse('24,80'));
        self::assertSame(248000, Money::parse('24.8'));
        self::assertSame(12345600, Money::parse('1.234,56'));
        self::assertSame(248000, Money::parse('24,80 €'));
        self::assertSame(1, Money::parse('0,00005'));
        self::assertNull(Money::parse('-3'));
        self::assertNull(Money::parse('abc'));
        self::assertNull(Money::parse(''));
    }

    public function testDbRoundTrip(): void
    {
        self::assertSame('24.8000', Money::toDb(248000));
        self::assertSame('0.0050', Money::toDb(50));
        self::assertSame(248000, Money::fromDb('24.8000'));
    }

    public function testFormatDe(): void
    {
        self::assertSame('24,80', Money::formatDe(248000));
        self::assertSame('1.234,57', Money::formatDe(12345678));
        self::assertSame('0,01', Money::formatDe(50));
    }

    public function testCartonOfTenKilosGivesPricePerKilo(): void
    {
        // 1 Karton = 10 kg für 24,80 € → 2,48 €/kg (Beispiel aus dem Auftrag)
        $cmp = Units::comparisonPrice(248000, 10000, 'order', 100000, 'kg');
        self::assertSame(['amount' => 24800, 'base' => 'kg'], $cmp);
    }

    public function testCanOf4250GramsGivesPricePerKilo(): void
    {
        // Dose 4.250 g für 12,75 € → 3,00 €/kg
        $cmp = Units::comparisonPrice(127500, 10000, 'order', 42500000, 'g');
        self::assertSame(['amount' => 30000, 'base' => 'kg'], $cmp);
    }

    public function testPricePerHundredGrams(): void
    {
        // 1,99 € je 100 g → 19,90 €/kg
        self::assertSame(['amount' => 199000, 'base' => 'kg'], Units::comparisonPrice(19900, 1000000, 'g', null, null));
    }

    public function testPricePerPieceAndMillilitre(): void
    {
        self::assertSame(['amount' => 5000, 'base' => 'stk'], Units::comparisonPrice(120000, 240000, 'stk', null, null));
        self::assertSame(['amount' => 40000, 'base' => 'l'], Units::comparisonPrice(35000, 8750000, 'ml', null, null));
    }

    public function testNoConversionWithoutKnownPack(): void
    {
        self::assertNull(Units::comparisonPrice(248000, 10000, 'order', null, null));
        self::assertNull(Units::comparisonPrice(248000, 10000, 'order', 100000, 'Karton'));
        self::assertNull(Units::comparisonPrice(248000, 0, 'kg', null, null));
        self::assertNull(Units::comparisonPrice(248000, 10000, 'Kiste', null, null));
    }
}
