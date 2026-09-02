<?php

namespace App\Support\Calculators;

use InvalidArgumentException;

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
     * A hybrid/flexi term loan's two-stage repayment: interest-only on the
     * full principal for the initial tenure, then standard reducing-balance
     * EMI (principal + interest) on that same principal amortized over the
     * subsequent tenure — reusing calculate() for that second stage rather
     * than re-deriving the EMI formula. The same annual rate applies to both
     * stages; nothing in the schema (LoanProduct/LenderProduct) has a basis
     * for a separate initial-stage rate, so none is invented here.
     *
     * @return array{initial_emi: float, subsequent_emi: float, initial_tenure_months: int, subsequent_tenure_months: int, total_interest: float, total_repayment: float, total_tenure_months: int}
     */
    public static function calculateHybrid(
        float $principal,
        float $annualRatePercent,
        int $initialTenureMonths,
        int $subsequentTenureMonths,
    ): array {
        if ($principal <= 0) {
            throw new InvalidArgumentException('Principal must be greater than zero.');
        }

        if ($initialTenureMonths <= 0) {
            throw new InvalidArgumentException('Initial tenure must be greater than zero months.');
        }

        if ($subsequentTenureMonths <= 0) {
            throw new InvalidArgumentException('Subsequent tenure must be greater than zero months.');
        }

        $monthlyRate = $annualRatePercent / 12 / 100;
        $initialEmi = round($principal * $monthlyRate, 2);

        $subsequentStage = self::calculate($principal, $annualRatePercent, $subsequentTenureMonths);

        $totalInterest = round(($initialEmi * $initialTenureMonths) + $subsequentStage['total_interest'], 2);
        $totalRepayment = round($principal + $totalInterest, 2);

        return [
            'initial_emi' => $initialEmi,
            'subsequent_emi' => $subsequentStage['emi'],
            'initial_tenure_months' => $initialTenureMonths,
            'subsequent_tenure_months' => $subsequentTenureMonths,
            'total_interest' => $totalInterest,
            'total_repayment' => $totalRepayment,
            'total_tenure_months' => $initialTenureMonths + $subsequentTenureMonths,
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
        return self::aggregateYearly(self::monthlySchedule($principal, $annualRatePercent, $tenureMonths));
    }

    /**
     * The full month-by-month schedule for a hybrid loan's *entire* tenure —
     * interest-only months during the initial tenure (principal untouched),
     * followed by monthlySchedule()'s ordinary reducing-balance months for
     * the subsequent tenure, with month/year renumbered to be absolute across
     * the whole loan rather than restarting at month 1 for the second stage.
     * Each row's 'stage' key ('initial'|'subsequent') is what the "full
     * breakdown" view groups and labels by.
     *
     * @return array<int, array{month: int, year: int, month_in_year: int, stage: string, principal_paid: float, interest_paid: float, total_paid: float, balance: float}>
     */
    public static function hybridMonthlySchedule(
        float $principal,
        float $annualRatePercent,
        int $initialTenureMonths,
        int $subsequentTenureMonths,
    ): array {
        if ($principal <= 0 || $initialTenureMonths <= 0 || $subsequentTenureMonths <= 0) {
            return [];
        }

        $monthlyRate = $annualRatePercent / 12 / 100;
        $interestOnlyPayment = round($principal * $monthlyRate, 2);
        $schedule = [];

        for ($month = 1; $month <= $initialTenureMonths; $month++) {
            $schedule[] = [
                'month' => $month,
                'year' => (int) ceil($month / 12),
                'month_in_year' => (($month - 1) % 12) + 1,
                'stage' => 'initial',
                'principal_paid' => 0.0,
                'interest_paid' => $interestOnlyPayment,
                'total_paid' => $interestOnlyPayment,
                'balance' => round($principal, 2),
            ];
        }

        foreach (self::monthlySchedule($principal, $annualRatePercent, $subsequentTenureMonths) as $row) {
            $absoluteMonth = $initialTenureMonths + $row['month'];

            $schedule[] = [
                'month' => $absoluteMonth,
                'year' => (int) ceil($absoluteMonth / 12),
                'month_in_year' => (($absoluteMonth - 1) % 12) + 1,
                'stage' => 'subsequent',
                'principal_paid' => $row['principal_paid'],
                'interest_paid' => $row['interest_paid'],
                'total_paid' => $row['total_paid'],
                'balance' => $row['balance'],
            ];
        }

        return $schedule;
    }

    /**
     * hybridMonthlySchedule() aggregated into year-by-year totals, the same
     * shape yearlySchedule() produces, so both can share one Blade rendering
     * pattern for the "full breakdown" table.
     *
     * @return array<int, array{year: int, principal_paid: float, interest_paid: float, total_paid: float, balance: float}>
     */
    public static function hybridYearlySchedule(
        float $principal,
        float $annualRatePercent,
        int $initialTenureMonths,
        int $subsequentTenureMonths,
    ): array {
        return self::aggregateYearly(self::hybridMonthlySchedule($principal, $annualRatePercent, $initialTenureMonths, $subsequentTenureMonths));
    }

    /**
     * @param  array<int, array{year: int, principal_paid: float, interest_paid: float, balance: float}>  $monthlySchedule
     * @return array<int, array{year: int, principal_paid: float, interest_paid: float, total_paid: float, balance: float}>
     */
    private static function aggregateYearly(array $monthlySchedule): array
    {
        $years = [];

        foreach ($monthlySchedule as $row) {
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
