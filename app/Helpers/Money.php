<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Beträge und Mengen als ganze Zahlen in Zehntausendsteln (Skala 4), passend zu DECIMAL(12,4).
 * Kein float im Rechenweg – 24,80 € = 248000.
 */
final class Money
{
    public const SCALE = 10000;

    /**
     * "24,80", "24.8", "1.234,56" → 248000 / 12345600. Negativ, leer oder ungültig → null.
     * Mehr als 4 Nachkommastellen werden kaufmännisch gerundet.
     */
    public static function parse(mixed $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        if (is_int($raw)) {
            return $raw >= 0 ? $raw * self::SCALE : null;
        }
        $s = str_replace([' ', "\u{00A0}", '€'], '', trim((string) $raw));
        if ($s === '') {
            return null;
        }
        // Tausenderpunkt nur, wenn danach ein Komma kommt ("1.234,56")
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
        }
        $s = str_replace(',', '.', $s);
        if (!preg_match('/^(\d{1,8})(?:\.(\d+))?$/', $s, $m)) {
            return null;
        }
        $frac = $m[2] ?? '';
        $units = (int) $m[1] * self::SCALE + (int) str_pad(substr($frac, 0, 4), 4, '0');
        if (strlen($frac) > 4 && (int) $frac[4] >= 5) {
            $units++;
        }

        return $units;
    }

    /** DECIMAL-String aus der DB ("24.8000") → 248000 */
    public static function fromDb(mixed $raw): ?int
    {
        return $raw === null || $raw === '' ? null : self::parse((string) $raw);
    }

    /** 248000 → "24.8000" für DECIMAL(12,4) */
    public static function toDb(int $units): string
    {
        return intdiv($units, self::SCALE) . '.' . str_pad((string) ($units % self::SCALE), 4, '0', STR_PAD_LEFT);
    }

    /** a × b / c mit kaufmännischer Rundung, alles Skala 4 bzw. ganzzahlig */
    public static function mulDiv(int $a, int $b, int $c): int
    {
        if ($c <= 0) {
            throw new \InvalidArgumentException('Divisor muss positiv sein.');
        }
        $num = $a * $b;

        return intdiv($num + intdiv($c, 2), $c);
    }

    /** 248000 → "24,80" (Anzeige, 2 Nachkommastellen) */
    public static function formatDe(int $units, int $decimals = 2): string
    {
        $div = 10 ** (4 - $decimals);
        $rounded = intdiv($units + intdiv($div, 2), $div);
        $whole = intdiv($rounded, 10 ** $decimals);
        $frac = str_pad((string) ($rounded % 10 ** $decimals), $decimals, '0', STR_PAD_LEFT);

        return number_format($whole, 0, ',', '.') . ($decimals > 0 ? ',' . $frac : '');
    }
}
