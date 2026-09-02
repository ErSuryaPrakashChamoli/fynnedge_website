<?php

namespace App\Support\Calculators;

class SipCalculator
{
    /**
     * Future value of a monthly SIP — annuity-due (investment made at the
     * start of each month, the market convention for SIP illustrations).
     *
     * @return array{invested_amount: float, estimated_returns: float, maturity_value: float}
     */
    public static function monthly(float $monthlyInvestment, float $annualRatePercent, int $tenureMonths): array
    {
        return self::series($monthlyInvestment, $annualRatePercent / 12 / 100, $tenureMonths);
    }

    /**
     * Future value of a daily SIP — annuity-due, using a 365-day year for the
     * daily rate.
     *
     * @return array{invested_amount: float, estimated_returns: float, maturity_value: float}
     */
    public static function daily(float $dailyInvestment, float $annualRatePercent, int $tenureDays): array
    {
        return self::series($dailyInvestment, $annualRatePercent / 365 / 100, $tenureDays);
    }

    /**
     * @return array{invested_amount: float, estimated_returns: float, maturity_value: float}
     */
    private static function series(float $installment, float $ratePerPeriod, int $periods): array
    {
        if ($installment <= 0 || $periods <= 0) {
            return ['invested_amount' => 0.0, 'estimated_returns' => 0.0, 'maturity_value' => 0.0];
        }

        $investedAmount = $installment * $periods;

        if ($ratePerPeriod <= 0) {
            return [
                'invested_amount' => round($investedAmount, 2),
                'estimated_returns' => 0.0,
                'maturity_value' => round($investedAmount, 2),
            ];
        }

        $maturityValue = $installment
            * ((((1 + $ratePerPeriod) ** $periods) - 1) / $ratePerPeriod)
            * (1 + $ratePerPeriod);

        return [
            'invested_amount' => round($investedAmount, 2),
            'estimated_returns' => round($maturityValue - $investedAmount, 2),
            'maturity_value' => round($maturityValue, 2),
        ];
    }
}
