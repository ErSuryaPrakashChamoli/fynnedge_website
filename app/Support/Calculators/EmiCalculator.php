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

    /**
     * Year-by-year reducing-balance amortization. The final year covers
     * whatever's left of the tenure (e.g. a 66-month tenure produces 5 full
     * years plus one 6-month year) rather than padding to a whole year.
     *
     * @return array<int, array{year: int, principal_paid: float, interest_paid: float, balance: float}>
     */
    public static function yearlySchedule(float $principal, float $annualRatePercent, int $tenureMonths): array
    {
        if ($principal <= 0 || $tenureMonths <= 0) {
            return [];
        }

        $emi = self::calculate($principal, $annualRatePercent, $tenureMonths)['emi'];
        $monthlyRate = $annualRatePercent / 12 / 100;

        $balance = $principal;
        $year = 1;
        $yearPrincipal = 0.0;
        $yearInterest = 0.0;
        $schedule = [];

        for ($month = 1; $month <= $tenureMonths; $month++) {
            $interestComponent = $balance * $monthlyRate;
            // The rounded (to-the-paisa) EMI drifts fractionally short of the exact
            // theoretical value over many months. Real lenders absorb that in the
            // final instalment rather than leaving a few paise owing forever — so
            // the last payment clears whatever balance is actually left, exactly.
            $principalComponent = $month === $tenureMonths ? $balance : min($emi - $interestComponent, $balance);
            $balance = max($balance - $principalComponent, 0.0);

            $yearPrincipal += $principalComponent;
            $yearInterest += $interestComponent;

            if ($month % 12 === 0 || $month === $tenureMonths) {
                $schedule[] = [
                    'year' => $year,
                    'principal_paid' => round($yearPrincipal, 2),
                    'interest_paid' => round($yearInterest, 2),
                    'balance' => round($balance, 2),
                ];

                $year++;
                $yearPrincipal = 0.0;
                $yearInterest = 0.0;
            }
        }

        return $schedule;
    }
}
