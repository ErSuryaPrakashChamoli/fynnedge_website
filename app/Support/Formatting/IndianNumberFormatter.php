<?php

namespace App\Support\Formatting;

class IndianNumberFormatter
{
    /**
     * Formats a number using the Indian digit-grouping convention — the last
     * three digits together, then pairs of two for the rest (e.g. 8,00,000
     * and 1,23,45,678), rather than PHP's number_format() default of
     * thousands throughout (800,000).
     */
    public static function format(int|float $amount): string
    {
        $amount = (int) round($amount);
        $isNegative = $amount < 0;
        $digits = (string) abs($amount);

        if (strlen($digits) <= 3) {
            return ($isNegative ? '-' : '').$digits;
        }

        $lastThree = substr($digits, -3);
        $remaining = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($digits, 0, -3));

        return ($isNegative ? '-' : '').$remaining.','.$lastThree;
    }
}
