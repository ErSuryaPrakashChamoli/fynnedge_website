<?php

namespace App\Support\Calculators;

class FdCalculator
{
    /**
     * Compound interest maturity value — quarterly compounding, the standard
     * convention Indian banks use for fixed deposits.
     *
     * @return array{maturity_value: float, total_interest: float}
     */
    public static function calculate(float $principal, float $annualRatePercent, int $tenureMonths, int $compoundingsPerYear = 4): array
    {
        if ($principal <= 0 || $tenureMonths <= 0) {
            return ['maturity_value' => 0.0, 'total_interest' => 0.0];
        }

        if ($annualRatePercent <= 0) {
            return ['maturity_value' => round($principal, 2), 'total_interest' => 0.0];
        }

        $years = $tenureMonths / 12;
        $ratePerPeriod = $annualRatePercent / 100 / $compoundingsPerYear;
        $periods = $compoundingsPerYear * $years;

        $maturityValue = $principal * (1 + $ratePerPeriod) ** $periods;

        return [
            'maturity_value' => round($maturityValue, 2),
            'total_interest' => round($maturityValue - $principal, 2),
        ];
    }
}
