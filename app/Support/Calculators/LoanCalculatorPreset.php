<?php

namespace App\Support\Calculators;

use App\Enums\LoanCategory;
use App\Models\LoanProduct;

/**
 * Slider ranges, validation bounds and starting values for the loan
 * calculator — resolved from the published LoanProduct row for a category,
 * not hardcoded here. LoanProduct is the single source of truth (admin-
 * editable via LoanProductResource); this class only shapes that row into
 * what the calculator component needs.
 *
 * Deliberately returns null — no calculator — for a category with no
 * complete admin configuration, rather than silently falling back to made-up
 * numbers nobody set. Credit Card always returns null: it doesn't repay on
 * a fixed EMI schedule, so there's nothing here to configure for it.
 *
 * A specific lender can already state its own narrower range on
 * lender_products (min_amount/max_amount/min_tenure_months/
 * max_tenure_months/interest_rate_from/interest_rate_to). This class reads
 * only the generic product-level configuration for now; a future per-lender
 * calculator can layer a LenderProduct override on top of for()'s result
 * without restructuring either table.
 */
class LoanCalculatorPreset
{
    /**
     * @return array{label: string, min_amount: float, max_amount: float, default_amount: float, min_rate: float, max_rate: float, default_rate: float, min_years: int, max_years: int, default_years: int, rate_note: ?string, is_hybrid: bool, default_initial_tenure_months: ?int}|null
     */
    public static function for(LoanCategory $category): ?array
    {
        if ($category === LoanCategory::CreditCard) {
            return null;
        }

        $product = static::product($category);

        if (! $product || $product->max_amount === null || $product->max_tenure_months === null
            || $product->min_interest_rate === null || $product->max_interest_rate === null) {
            return null;
        }

        $minAmount = (float) ($product->min_amount ?? 10_000);
        $maxAmount = (float) $product->max_amount;
        $minTenureMonths = (int) ($product->min_tenure_months ?? 3);
        $maxTenureMonths = (int) $product->max_tenure_months;
        $minRate = (float) $product->min_interest_rate;
        $maxRate = (float) $product->max_interest_rate;

        return [
            'label' => $category->getLabel(),
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount,
            'default_amount' => self::clamp((float) ($product->default_amount ?? $minAmount), $minAmount, $maxAmount),
            'min_rate' => $minRate,
            'max_rate' => $maxRate,
            'default_rate' => self::clamp((float) ($product->default_interest_rate ?? $minRate), $minRate, $maxRate),
            'min_years' => max(1, (int) ceil($minTenureMonths / 12)),
            'max_years' => max(1, (int) floor($maxTenureMonths / 12)),
            'default_years' => self::clampInt(
                (int) round(($product->default_tenure_months ?? $minTenureMonths) / 12),
                max(1, (int) ceil($minTenureMonths / 12)),
                max(1, (int) floor($maxTenureMonths / 12)),
            ),
            'rate_note' => $product->interest_rate_note,
            'is_hybrid' => $category->isHybridRepayment(),
            'default_initial_tenure_months' => $product->default_initial_tenure_months,
        ];
    }

    /**
     * @return array<int, LoanCategory>
     */
    public static function supportedCategories(): array
    {
        return array_values(array_filter(
            LoanCategory::cases(),
            fn (LoanCategory $category) => self::for($category) !== null,
        ));
    }

    /**
     * The published LoanProduct record backing a category's calculator —
     * exposed publicly (unlike the private product() lookup below) so
     * callers that need the full model, not just the shaped preset array
     * (e.g. the calculator's CTAs, explanation copy, FAQs), can resolve it
     * without duplicating this query.
     */
    public static function productFor(LoanCategory $category): ?LoanProduct
    {
        return static::product($category);
    }

    private static function product(LoanCategory $category): ?LoanProduct
    {
        return LoanProduct::query()
            ->published()
            ->where('category', $category)
            ->orderBy('id')
            ->first();
    }

    private static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    private static function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
