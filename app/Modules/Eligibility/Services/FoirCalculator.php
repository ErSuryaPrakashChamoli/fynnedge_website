<?php

namespace App\Modules\Eligibility\Services;

use App\Models\LenderProduct;
use App\Support\Calculators\EmiCalculator;

class FoirCalculator
{
    /**
     * Fixed Obligation to Income Ratio, using the lender's own rate/tenure — FOIR is
     * inherently lender-specific since the proposed EMI depends on that lender's terms.
     */
    public function calculate(
        ?float $loanAmountRequested,
        ?float $totalMonthlyIncome,
        float $existingEmiAmount,
        ?int $preferredTenureMonths,
        LenderProduct $lenderProduct,
    ): ?float {
        if (! $loanAmountRequested || $loanAmountRequested <= 0) {
            return null;
        }

        if (! $totalMonthlyIncome || $totalMonthlyIncome <= 0) {
            return null;
        }

        $rate = (float) ($lenderProduct->interest_rate_from ?? 12.0);
        $tenure = $preferredTenureMonths ?? $lenderProduct->max_tenure_months ?? 60;

        $emi = EmiCalculator::calculate($loanAmountRequested, $rate, $tenure)['emi'];

        return round((($existingEmiAmount + $emi) / $totalMonthlyIncome) * 100, 2);
    }
}
