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
