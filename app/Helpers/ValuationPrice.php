<?php

declare(strict_types=1);

namespace App\Helpers;

final class ValuationPrice
{
    public static function parse(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = str_replace(',', '.', trim((string) $raw));
        if ($s === '' || !is_numeric($s)) {
            return null;
        }
        $n = (float) $s;
        if ($n < 0) {
            return null;
        }

        return round($n, 2);
    }
}
