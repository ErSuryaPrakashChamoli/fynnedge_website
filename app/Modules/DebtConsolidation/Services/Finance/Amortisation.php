<?php

namespace App\Modules\DebtConsolidation\Services\Finance;

use App\Modules\DebtConsolidation\DataTransferObjects\CashFlowSchedule;

/**
 * Reducing-balance maths with paisa rounding per period: each month's
 * interest is rounded half-up, then the payment, and the final payment clears
 * the balance exactly — the way a lender's repayment schedule is produced.
 * Rates are annual percentages (18.0 = 18 % p.a.); monthly interest is
 * balance × rate ÷ 1200.
 */
final class Amortisation
{
    /**
     * One month's interest on $balance, rounded half-up to the paisa. Exact
     * (integer paise) for rates with up to four decimals.
     */
    public static function interest(float $balance, float $annualRatePercent): float
    {
        if ($balance <= 0 || $annualRatePercent <= 0) {
            return 0.0;
        }

        $scaled = Money::scaledRate($annualRatePercent);
        $paise = Money::toPaise($balance);

        if ($scaled === null || $paise > intdiv(PHP_INT_MAX, 4 * max(1, $scaled))) {
            return Money::round($balance * $annualRatePercent / 1200);
        }

        return Money::fromPaise(Money::halfUpDivide($paise * $scaled, 1200 * Money::RATE_SCALE));
    }

    /**
     * Standard EMI, rounded to the paisa. Agrees with
     * App\Support\Calculators\EmiCalculator::calculate()['emi'].
     */
    public static function emi(float $principal, float $annualRatePercent, int $months): float
    {
        if ($principal <= 0 || $months <= 0) {
            return 0.0;
        }

        $rate = max(0.0, $annualRatePercent) / 1200;

        if ($rate <= 0) {
            return Money::round($principal / $months);
        }

        $factor = (1 + $rate) ** $months;

        return Money::round($principal * $rate * $factor / ($factor - 1));
    }

    /**
     * Pays $payment every month for at most $months months; the last payment
     * settles whatever balance remains (the usual final-instalment
     * adjustment). A payment at or below the monthly interest lets the balance
     * grow, and the last month then settles the grown balance.
     */
    public static function termSchedule(float $principal, float $annualRatePercent, float $payment, int $months): CashFlowSchedule
    {
        $balance = Money::round($principal);
        $payment = Money::round($payment);
        $payments = [];
        $interest = [];

        for ($month = 1; $month <= $months && $balance > 0; $month++) {
            $charged = self::interest($balance, $annualRatePercent);
            $due = Money::round($balance + $charged);

            if ($month === $months || $payment >= $due) {
                $payments[] = $due;
                $interest[] = $charged;

                break;
            }

            $payments[] = $payment;
            $interest[] = $charged;
            $balance = Money::round($due - $payment);
        }

        return new CashFlowSchedule($payments, $interest);
    }

    /**
     * Whether a monthly payment pays more than the first month's interest, i.e.
     * whether the debt ever shrinks at that payment.
     */
    public static function covers(float $principal, float $annualRatePercent, float $payment): bool
    {
        return $payment > self::interest($principal, $annualRatePercent);
    }

    /**
     * Interest every month and the principal in the last month.
     */
    public static function interestOnlySchedule(float $principal, float $annualRatePercent, int $months): CashFlowSchedule
    {
        $principal = Money::round($principal);
        $charged = self::interest($principal, $annualRatePercent);
        $payments = array_fill(0, max(0, $months - 1), $charged);
        $payments[] = Money::round($charged + $principal);

        return new CashFlowSchedule($payments, array_fill(0, $months, $charged));
    }

    /**
     * The annual rate (%) at which $months payments of $payment repay
     * $principal exactly — solved by bisection with a fixed number of steps so
     * the answer is deterministic. Null when the payments do not even cover
     * the principal.
     */
    public static function impliedAnnualRate(float $principal, float $payment, int $months): ?float
    {
        if ($principal <= 0 || $payment <= 0 || $months <= 0) {
            return null;
        }

        $totalPaid = $payment * $months;

        if ($totalPaid < $principal - 0.005) {
            return null;
        }

        if ($totalPaid - $principal < 0.01) {
            return 0.0;
        }

        $low = 0.0;
        $high = 1.0;

        for ($step = 0; $step < 200; $step++) {
            $mid = ($low + $high) / 2;
            $presentValue = $payment * (1 - (1 + $mid) ** -$months) / $mid;

            if ($presentValue > $principal) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return ($low + $high) / 2 * 1200;
    }
}
