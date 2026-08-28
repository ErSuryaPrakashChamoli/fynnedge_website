<?php

namespace App\Support\Calculators;

class EmiCalculator
{
    /**
     * @return array{emi: float, total_payment: float, total_interest: float}
     */
    public static function calculate(float $principal, float $annualRatePercent, int $tenureMonths): array
    {
        if ($principal <= 0 || $tenureMonths <= 0) {
            return ['emi' => 0.0, 'total_payment' => 0.0, 'total_interest' => 0.0];
        }

        if ($annualRatePercent <= 0) {
            $emi = $principal / $tenureMonths;
        } else {
            $monthlyRate = $annualRatePercent / 12 / 100;
            $factor = (1 + $monthlyRate) ** $tenureMonths;
            $emi = $principal * $monthlyRate * $factor / ($factor - 1);
        }

        $totalPayment = $emi * $tenureMonths;

        return [
            'emi' => round($emi, 2),
            'total_payment' => round($totalPayment, 2),
            'total_interest' => round($totalPayment - $principal, 2),
        ];
    }
}
