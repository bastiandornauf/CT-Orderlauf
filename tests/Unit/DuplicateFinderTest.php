<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DuplicateFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DuplicateFinderTest extends TestCase
{
    /** Echte oder sehr wahrscheinliche Dubletten – sollen vorgeschlagen werden */
    public static function likelyDuplicates(): array
    {
        return [
            ['Oliven Schwarz ohne Kern', 'Schwarze Oliven, entsteint'],
            ['Röstzwiebeln', 'Röstzwiebeln 2,5 kg'],
            ['Zitronen(5kg)', 'Zitrone'],
            ['Brokkoli', 'Brokoli'],
            ['Karotten / Möhren', 'Möhren'],
            ['Salatmayonnaise 50 %', 'Salat-Mayonnaise 50%'],
        ];
    }

    /** Varianten aus dem echten Bestand – sollen NICHT vorgeschlagen werden */
    public static function variants(): array
    {
        return [
            ['Kräuter, Petersilie, glatt', 'Kräuter, Petersilie, krause'],
            ['Latex Handschuhe Gr.L ungepudert', 'Latex Handschuhe Gr.XL ungepudert'],
            ['Siegelrandbeutel 160×240 mm', 'Siegelrandbeutel 150×250 mm'],
            ['H - Milch 1,5 %', 'H - Milch 3,5 %'],
            ['Kochwein rot', 'Kochwein weiß'],
            ['Eis Vanille (TK)', 'Eis Schokolade (TK)'],
            ['Eis Vanille (TK)', 'Eis Bum Bum'],
            ['Balsamicoessig, dunkel', 'Balsamicoessig, hell'],
            ['Oliven Schwarz ohne Kern', 'Oliven grün mit Stein'],
            ['Tomatenketchup Portionen', 'Mayonnaise 80 % Portionen'],
            ['Kräuter, Basilikum', 'Pesto Verde Basilikum'],
        ];
    }

    #[DataProvider('likelyDuplicates')]
    public function testSuggestsDuplicates(string $a, string $b): void
    {
        $r = (new DuplicateFinder())->compare(['name' => $a], ['name' => $b]);
        self::assertGreaterThanOrEqual(DuplicateFinder::MIN_SCORE, $r['score'], implode('; ', $r['reasons']));
    }

    #[DataProvider('variants')]
    public function testKeepsVariantsApart(string $a, string $b): void
    {
        $r = (new DuplicateFinder())->compare(['name' => $a], ['name' => $b]);
        self::assertLessThan(DuplicateFinder::MIN_SCORE, $r['score'], implode('; ', $r['reasons']));
    }

    public function testSameSkuIsStrongSignalEvenWithDifferentNames(): void
    {
        $r = (new DuplicateFinder())->compare(
            ['name' => 'Honig in Spritzflaschen', 'skus' => ['3:447621']],
            ['name' => 'Blütenhonig, Spender', 'skus' => ['3:447621']]
        );
        self::assertGreaterThanOrEqual(0.9, $r['score']);
    }

    public function testAliasCountsForComparison(): void
    {
        $r = (new DuplicateFinder())->compare(
            ['name' => 'Farmfrites Steakhouse Fries', 'alias' => 'Pommes'],
            ['name' => 'Pommes frites 2,5 kg']
        );
        self::assertGreaterThanOrEqual(DuplicateFinder::MIN_SCORE, $r['score'], implode('; ', $r['reasons']));
    }
}
