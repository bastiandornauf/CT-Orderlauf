<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\ValuationPrice;
use PHPUnit\Framework\TestCase;

final class ValuationPriceTest extends TestCase
{
    public function testParsesGermanDecimalComma(): void
    {
        self::assertSame(2.48, ValuationPrice::parse('2,48'));
    }

    public function testEmptyAndInvalidValuesAreUnknown(): void
    {
        self::assertNull(ValuationPrice::parse(''));
        self::assertNull(ValuationPrice::parse(null));
        self::assertNull(ValuationPrice::parse('abc'));
        self::assertNull(ValuationPrice::parse('-1'));
    }
}
