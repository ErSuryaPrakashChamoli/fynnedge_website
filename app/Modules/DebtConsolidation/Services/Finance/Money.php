<?php

namespace App\Modules\DebtConsolidation\Services\Finance;

/**
 * Paisa rounding used by every engine step: half-up ("commercial") rounding,
 * the way lenders round interest and charges.
 *
 * PHP 8.4+ round() rounds the exact binary value of a float, so a computed
 * ₹2,029.755 held as 2029.75499999… would round DOWN. Percentages and
 * interest at rates with up to four decimals are therefore computed on whole
 * paise with integer arithmetic (percentage(), and Amortisation::interest()),
 * which makes every half-paisa tie round up exactly.
 */
final class Money
{
    /** Rates/percentages are exact up to this many decimals (e.g. 10.4975 %). */
    public const RATE_SCALE = 10000;

    public static function round(float $amount): float
    {
        $rounded = round($amount, 2);

        return $rounded == 0.0 ? 0.0 : $rounded;
    }

    /**
     * @param  iterable<float>  $amounts
     */
    public static function sum(iterable $amounts): float
    {
        $paise = 0;

        foreach ($amounts as $amount) {
            $paise += self::toPaise($amount);
        }

        return self::fromPaise($paise);
    }

    /**
     * $percent % of $amount, rounded half-up to the paisa.
     */
    public static function percentage(float $amount, float $percent): float
    {
        $scaled = self::scaledRate($percent);

        if ($scaled === null) {
            return self::round($amount * $percent / 100);
        }

        $numerator = self::toPaise($amount) * $scaled;

        return self::fromPaise(self::halfUpDivide($numerator, 100 * self::RATE_SCALE));
    }

    public static function percentOf(float $part, float $whole): ?float
    {
        if ($whole <= 0.0) {
            return null;
        }

        return round($part / $whole * 100, 2);
    }

    public static function toPaise(float $amount): int
    {
        return (int) round($amount * 100);
    }

    public static function fromPaise(int $paise): float
    {
        return $paise === 0 ? 0.0 : $paise / 100;
    }

    /**
     * The rate times RATE_SCALE as an integer when the rate has at most four
     * decimals and the product cannot overflow; null otherwise (implied rates
     * solved by bisection, which never sit exactly on a half-paisa tie).
     */
    public static function scaledRate(float $rate): ?int
    {
        $scaled = $rate * self::RATE_SCALE;
        $whole = round($scaled);

        if ($rate < 0 || abs($scaled - $whole) > 1e-6 || $whole > 10_000_000) {
            return null;
        }

        return (int) $whole;
    }

    /**
     * Integer division rounded half away from zero.
     */
    public static function halfUpDivide(int $numerator, int $denominator): int
    {
        if ($numerator < 0) {
            return -self::halfUpDivide(-$numerator, $denominator);
        }

        if ($numerator > intdiv(PHP_INT_MAX - $denominator, 2)) {
            return (int) round($numerator / $denominator);
        }

        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }
}
