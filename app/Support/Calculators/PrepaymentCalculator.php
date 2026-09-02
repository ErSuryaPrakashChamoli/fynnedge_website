<?php

namespace App\Support\Calculators;

class PrepaymentCalculator
{
    /**
     * A lumpsum prepayment can either shorten the tenure (same EMI, fewer
     * months) or lower the EMI (same tenure, smaller instalment). Total
     * payment/interest figures use the same emi × tenure approximation
     * EmiCalculator::calculate() itself uses, not the exact monthly sum —
     * consistent with how every other summary figure in this app is derived.
     *
     * @return array{original_emi: float, new_emi: float, original_tenure_months: int, new_tenure_months: int, tenure_reduced_months: int, original_total_interest: float, new_total_interest: float, interest_saved: float}
     */
    public static function calculate(
        float $outstandingPrincipal,
        float $annualRatePercent,
        int $remainingTenureMonths,
        float $prepaymentAmount,
        string $mode,
    ): array {
        if ($outstandingPrincipal <= 0 || $remainingTenureMonths <= 0) {
            return [
                'original_emi' => 0.0, 'new_emi' => 0.0,
                'original_tenure_months' => 0, 'new_tenure_months' => 0, 'tenure_reduced_months' => 0,
                'original_total_interest' => 0.0, 'new_total_interest' => 0.0, 'interest_saved' => 0.0,
            ];
        }

        $prepaymentAmount = max(0.0, min($prepaymentAmount, $outstandingPrincipal));
        $newPrincipal = round($outstandingPrincipal - $prepaymentAmount, 2);

        $original = EmiCalculator::calculate($outstandingPrincipal, $annualRatePercent, $remainingTenureMonths);
        $originalEmi = $original['emi'];
        $originalTotalInterest = $original['total_interest'];

        if ($newPrincipal <= 0) {
            return [
                'original_emi' => $originalEmi, 'new_emi' => 0.0,
                'original_tenure_months' => $remainingTenureMonths, 'new_tenure_months' => 0,
                'tenure_reduced_months' => $remainingTenureMonths,
                'original_total_interest' => $originalTotalInterest, 'new_total_interest' => 0.0,
                'interest_saved' => $originalTotalInterest,
            ];
        }

        if ($mode === 'reduce_emi') {
            $new = EmiCalculator::calculate($newPrincipal, $annualRatePercent, $remainingTenureMonths);

            return [
                'original_emi' => $originalEmi, 'new_emi' => $new['emi'],
                'original_tenure_months' => $remainingTenureMonths, 'new_tenure_months' => $remainingTenureMonths,
                'tenure_reduced_months' => 0,
                'original_total_interest' => $originalTotalInterest, 'new_total_interest' => $new['total_interest'],
                'interest_saved' => round($originalTotalInterest - $new['total_interest'], 2),
            ];
        }

        $monthlyRate = $annualRatePercent / 12 / 100;

        $newTenureMonths = $monthlyRate > 0
            ? (int) ceil(-log(1 - ($newPrincipal * $monthlyRate) / $originalEmi) / log(1 + $monthlyRate))
            : (int) ceil($newPrincipal / $originalEmi);

        $newTenureMonths = max(1, min($newTenureMonths, $remainingTenureMonths));
        $newTotalInterest = round(($originalEmi * $newTenureMonths) - $newPrincipal, 2);

        return [
            'original_emi' => $originalEmi, 'new_emi' => $originalEmi,
            'original_tenure_months' => $remainingTenureMonths, 'new_tenure_months' => $newTenureMonths,
            'tenure_reduced_months' => $remainingTenureMonths - $newTenureMonths,
            'original_total_interest' => $originalTotalInterest, 'new_total_interest' => $newTotalInterest,
            'interest_saved' => round($originalTotalInterest - $newTotalInterest, 2),
        ];
    }
}
