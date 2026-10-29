<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Zentrale Einheiten für Gebinde und Vergleichspreise.
 * Unbekannte Einheiten ergeben null – es wird nie geraten.
 */
final class Units
{
    /** Erlaubte Gebinde-Einheiten (kanonisch) */
    public const PACK_UNITS = ['kg', 'g', 'l', 'ml', 'stk'];

    /** Schreibweisen aus dem Bestand → kanonische Einheit */
    private const ALIASES = [
        'kg' => 'kg', 'kilo' => 'kg', 'kilogramm' => 'kg',
        'g' => 'g', 'gr' => 'g', 'gramm' => 'g',
        'l' => 'l', 'ltr' => 'l', 'liter' => 'l',
        'ml' => 'ml',
        'stk' => 'stk', 'st' => 'stk', 'stück' => 'stk', 'stueck' => 'stk',
    ];

    /** Gebinde-Einheit → [Vergleichseinheit, Zähler, Nenner]: 1 g = 1/1000 kg */
    private const TO_BASE = [
        'kg' => ['kg', 1, 1],
        'g' => ['kg', 1, 1000],
        'l' => ['l', 1, 1],
        'ml' => ['l', 1, 1000],
        'stk' => ['stk', 1, 1],
    ];

    public const BASE_LABELS = ['kg' => 'kg', 'l' => 'l', 'stk' => 'Stück'];

    /**
     * Vergleichspreis (€/kg, €/l, €/Stück) aus einem Preis, alles in Skala 4 (siehe Money).
     *
     * per_unit = 'order': Preis gilt je Bestelleinheit → braucht ein bekanntes Gebinde.
     * per_unit = kg|g|l|ml|stk: Preis gilt je per_qty dieser Einheit.
     * Unbekannte oder unvollständige Angaben → null (keine geratene Umrechnung).
     *
     * @return array{amount: int, base: string}|null
     */
    public static function comparisonPrice(int $amount, int $perQty, string $perUnit, ?int $packSize, ?string $packUnit): ?array
    {
        if ($perQty <= 0) {
            return null;
        }
        if ($perUnit === 'order') {
            if ($packSize === null || $packSize <= 0 || $packUnit === null || !isset(self::TO_BASE[$packUnit])) {
                return null;
            }
            [$base, $num, $den] = self::TO_BASE[$packUnit];
            // Menge in Vergleichseinheit (Skala 4) = per_qty × Gebinde × num/den
            $qty = Money::mulDiv($perQty, $packSize * $num, Money::SCALE * $den);
        } elseif (isset(self::TO_BASE[$perUnit])) {
            [$base, $num, $den] = self::TO_BASE[$perUnit];
            $qty = Money::mulDiv($perQty, $num, $den);
        } else {
            return null;
        }
        if ($qty <= 0) {
            return null;
        }

        return ['amount' => Money::mulDiv($amount, Money::SCALE, $qty), 'base' => $base];
    }

    public static function normalizePackUnit(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $key = mb_strtolower(trim((string) $raw));
        $key = rtrim($key, '.');

        return self::ALIASES[$key] ?? null;
    }

    /** Positive Dezimalzahl mit Komma oder Punkt; sonst null. */
    public static function parsePositiveDecimal(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = str_replace(',', '.', trim((string) $raw));
        if ($s === '' || !is_numeric($s)) {
            return null;
        }
        $n = (float) $s;

        return $n > 0 ? round($n, 4) : null;
    }
}
