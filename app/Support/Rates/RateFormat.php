<?php

namespace App\Support\Rates;

use App\Enums\RateUnit;

/**
 * The one place a percentage becomes text, so every page shows a rate with
 * the same precision: always two decimals ("9.90%", "22.00%").
 */
final class RateFormat
{
    public static function percent(float $value): string
    {
        return number_format($value, 2).'%';
    }

    /**
     * "9.90%–22.00% p.a.", or "9.90% p.a." when only a starting rate is published.
     */
    public static function range(float $min, ?float $max, RateUnit $unit): string
    {
        $figures = $max !== null && $max > $min
            ? self::percent($min).'–'.self::percent($max)
            : self::percent($min);

        return "{$figures} {$unit->suffix()}";
    }
}
