<?php

namespace App\Support\Calculators;

use App\Enums\LoanCategory;

/**
 * Slider ranges and sensible starting values for the loan calculator, per loan
 * category. This is presentation-only — it shapes what a visitor sees while
 * playing with the calculator, not any eligibility or pricing decision (those
 * stay entirely in the database-driven eligibility engine and each lender's
 * own published rates). Indicative ranges only, same as the calculator's own
 * on-page disclaimer already says.
 */
class LoanCalculatorPreset
{
    /**
     * @return array{label: string, min_amount: int, max_amount: int, default_amount: int, min_rate: float, max_rate: float, default_rate: float, min_years: int, max_years: int, default_years: int}|null
     *                                                                                                                                                                                                     null for a category with no EMI-style repayment (e.g. Credit Card).
     */
    public static function for(LoanCategory $category): ?array
    {
        return match ($category) {
            LoanCategory::PersonalLoan => [
                'label' => 'Personal Loan',
                'min_amount' => 25_000, 'max_amount' => 4_000_000, 'default_amount' => 500_000,
                'min_rate' => 10.5, 'max_rate' => 24.0, 'default_rate' => 13.5,
                'min_years' => 1, 'max_years' => 7, 'default_years' => 3,
            ],
            LoanCategory::HomeLoan => [
                'label' => 'Home Loan',
                'min_amount' => 500_000, 'max_amount' => 100_000_000, 'default_amount' => 4_000_000,
                'min_rate' => 8.0, 'max_rate' => 12.5, 'default_rate' => 8.9,
                'min_years' => 5, 'max_years' => 30, 'default_years' => 20,
            ],
            LoanCategory::BusinessLoan => [
                'label' => 'Business Loan',
                'min_amount' => 100_000, 'max_amount' => 50_000_000, 'default_amount' => 1_000_000,
                'min_rate' => 11.0, 'max_rate' => 24.0, 'default_rate' => 15.0,
                'min_years' => 1, 'max_years' => 10, 'default_years' => 5,
            ],
            LoanCategory::LoanAgainstProperty => [
                'label' => 'Loan Against Property',
                'min_amount' => 500_000, 'max_amount' => 100_000_000, 'default_amount' => 3_000_000,
                'min_rate' => 9.0, 'max_rate' => 15.0, 'default_rate' => 10.5,
                'min_years' => 5, 'max_years' => 20, 'default_years' => 10,
            ],
            LoanCategory::CreditCard => null,
        };
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
}
