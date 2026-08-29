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
     * Month-by-month reducing-balance amortization — the source of truth the
     * yearly schedule is aggregated from.
     *
     * @return array<int, array{month: int, year: int, month_in_year: int, principal_paid: float, interest_paid: float, total_paid: float, balance: float}>
     */
    public static function monthlySchedule(float $principal, float $annualRatePercent, int $tenureMonths): array
    {
        if ($principal <= 0 || $tenureMonths <= 0) {
            return [];
        }

        $emi = self::calculate($principal, $annualRatePercent, $tenureMonths)['emi'];
        $monthlyRate = $annualRatePercent / 12 / 100;
        $balance = $principal;
        $schedule = [];

        for ($month = 1; $month <= $tenureMonths; $month++) {
            $interestComponent = $balance * $monthlyRate;
            // The rounded (to-the-paisa) EMI drifts fractionally short of the exact
            // theoretical value over many months. Real lenders absorb that in the
            // final instalment rather than leaving a few paise owing forever — so
            // the last payment clears whatever balance is actually left, exactly.
            $principalComponent = $month === $tenureMonths ? $balance : min($emi - $interestComponent, $balance);
            $balance = max($balance - $principalComponent, 0.0);

            $schedule[] = [
                'month' => $month,
                'year' => (int) ceil($month / 12),
                'month_in_year' => (($month - 1) % 12) + 1,
                'principal_paid' => round($principalComponent, 2),
                'interest_paid' => round($interestComponent, 2),
                'total_paid' => round($principalComponent + $interestComponent, 2),
                'balance' => round($balance, 2),
            ];
        }

        return $schedule;
    }

    /**
     * Year-by-year totals, aggregated from monthlySchedule() so a year's totals
     * always match the sum of the individual months shown for it. The final
     * year covers whatever's left of the tenure (e.g. a 66-month tenure
     * produces 5 full years plus one 6-month year) rather than padding to a
     * whole year.
     *
     * @return array<int, array{year: int, principal_paid: float, interest_paid: float, total_paid: float, balance: float}>
     */
    public static function yearlySchedule(float $principal, float $annualRatePercent, int $tenureMonths): array
    {
        $months = self::monthlySchedule($principal, $annualRatePercent, $tenureMonths);

        $years = [];

        foreach ($months as $row) {
            $year = $row['year'];
            $years[$year]['year'] ??= $year;
            $years[$year]['principal_paid'] = ($years[$year]['principal_paid'] ?? 0.0) + $row['principal_paid'];
            $years[$year]['interest_paid'] = ($years[$year]['interest_paid'] ?? 0.0) + $row['interest_paid'];
            $years[$year]['balance'] = $row['balance'];
        }

        return array_values(array_map(fn (array $year) => [
            'year' => $year['year'],
            'principal_paid' => round($year['principal_paid'], 2),
            'interest_paid' => round($year['interest_paid'], 2),
            'total_paid' => round($year['principal_paid'] + $year['interest_paid'], 2),
            'balance' => round($year['balance'], 2),
        ], $years));
    }
}
