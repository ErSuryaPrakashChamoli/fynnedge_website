<?php

namespace App\Support\Enquiries;

use App\Models\LoanProduct;
use App\Support\Formatting\IndianNumberFormatter;

/**
 * The amount a visitor may enquire for, taken from the product's own configured
 * limits so the form, its error message and the calculator all quote the same
 * numbers. Shared by the controller (validation) and the Blade component (the
 * helper text under the field) — one definition, never two that drift.
 */
class LoanEnquiryAmount
{
    /**
     * Fallbacks for a product whose limits an admin has not filled in yet. Wide
     * on purpose: their job is to stop nonsense (0, or a number with fifteen
     * digits), not to second-guess a product that has no stated range.
     */
    private const FALLBACK_MIN = 10000;

    private const FALLBACK_MAX = 100000000;

    /**
     * @return array{min: int, max: int}
     */
    public static function rangeFor(LoanProduct $loanProduct): array
    {
        $min = (int) ($loanProduct->min_amount ?: self::FALLBACK_MIN);
        $max = (int) ($loanProduct->max_amount ?: self::FALLBACK_MAX);

        // A product configured with only a minimum (or a mis-entered pair) must
        // not produce an impossible range that rejects every amount.
        return $max > $min ? ['min' => $min, 'max' => $max] : ['min' => $min, 'max' => self::FALLBACK_MAX];
    }

    /**
     * @param  array{min: int, max: int}  $range
     */
    public static function rangeMessage(array $range): string
    {
        return 'Enter an amount between ₹'.IndianNumberFormatter::format($range['min'])
            .' and ₹'.IndianNumberFormatter::format($range['max']).'.';
    }
}
