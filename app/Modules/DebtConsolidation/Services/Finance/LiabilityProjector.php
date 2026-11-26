<?php

namespace App\Modules\DebtConsolidation\Services\Finance;

use App\Modules\DebtConsolidation\DataTransferObjects\Assumptions;
use App\Modules\DebtConsolidation\DataTransferObjects\FlagRaised;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityProjection;
use App\Modules\DebtConsolidation\Enums\RepaymentStructure;
use App\Modules\DebtConsolidation\Enums\RiskFlag;

/**
 * Projects what keeping a liability costs from today.
 *
 * Amortising loans: when both the EMI and the remaining months are known the
 * contractual payments are used as they are (EMI × months, with the rate they
 * imply); this stays right even when the customer quotes a flat rate. With
 * only one of them the other is derived from the stated rate; with neither the
 * scenario tenure is assumed and flagged.
 *
 * $principal lets the caller project a reduced balance (partial
 * consolidation): amortising loans keep their EMI and finish sooner, revolving
 * balances keep their payment, interest-only facilities keep their term.
 */
final class LiabilityProjector
{
    public function project(LiabilityInput $liability, int $fallbackMonths, Assumptions $assumptions, ?float $principal = null): LiabilityProjection
    {
        $principal = Money::round($principal ?? $liability->outstandingPrincipal);
        $fallbackMonths = max(1, min($fallbackMonths, $assumptions->maxProjectionMonths));

        if ($principal <= 0) {
            return LiabilityProjection::empty();
        }

        return match ($liability->structure()) {
            RepaymentStructure::Amortising => $this->amortising($liability, $principal, $fallbackMonths, $assumptions),
            RepaymentStructure::Revolving => $this->revolving($liability, $principal, $fallbackMonths, $assumptions),
            RepaymentStructure::InterestOnly => $this->interestOnly($liability, $principal, $fallbackMonths),
        };
    }

    private function amortising(LiabilityInput $liability, float $principal, int $fallbackMonths, Assumptions $assumptions): LiabilityProjection
    {
        $payment = $liability->monthlyPayment !== null && $liability->monthlyPayment > 0 ? Money::round($liability->monthlyPayment) : null;
        $months = $liability->remainingMonths !== null && $liability->remainingMonths > 0 ? $liability->remainingMonths : null;
        $statedRate = $liability->annualInterestRate;
        $isPartial = $principal < Money::round($liability->outstandingPrincipal);
        $flags = [];

        if ($payment !== null && $months !== null) {
            $impliedAnnual = Amortisation::impliedAnnualRate($liability->outstandingPrincipal, $payment, $months);

            if ($impliedAnnual !== null) {
                $statedAgrees = $liability->annualInterestRate > 0
                    && abs($impliedAnnual - $liability->annualInterestRate) <= $assumptions->rateMismatchTolerancePoints;

                if ($liability->annualInterestRate > 0 && ! $statedAgrees) {
                    $flags[] = new FlagRaised(RiskFlag::RateMismatch, $liability->key, [
                        'stated_rate' => round($liability->annualInterestRate, 2),
                        'implied_rate' => round($impliedAnnual, 2),
                    ]);
                }

                // A stated rate that agrees with the EMI is the contract rate: schedule
                // on it exactly. Otherwise (e.g. a flat rate was quoted) the rate the
                // payments imply is the true reducing-balance cost.
                $rate = $statedAgrees ? $liability->annualInterestRate : $impliedAnnual;
                $schedule = Amortisation::termSchedule($principal, $rate, $payment, $isPartial ? $assumptions->maxProjectionMonths : $months);

                return new LiabilityProjection($schedule, $payment, $rate, $impliedAnnual, false, $flags);
            }

            $flags[] = new FlagRaised(RiskFlag::InconsistentLoanDetails, $liability->key, [
                'emi' => $payment,
                'remaining_months' => $months,
                'outstanding' => Money::round($liability->outstandingPrincipal),
            ]);
        }

        if ($payment !== null) {
            if (! Amortisation::covers($principal, $statedRate, $payment)) {
                $flags[] = new FlagRaised(RiskFlag::PaymentBelowInterest, $liability->key, ['payment' => $payment]);

                return new LiabilityProjection(
                    Amortisation::termSchedule($principal, $statedRate, $payment, $fallbackMonths),
                    $payment,
                    $liability->annualInterestRate,
                    null,
                    true,
                    $flags,
                );
            }

            return new LiabilityProjection(
                Amortisation::termSchedule($principal, $statedRate, $payment, $assumptions->maxProjectionMonths),
                $payment,
                $liability->annualInterestRate,
                null,
                false,
                $flags,
            );
        }

        $assumed = $months === null;

        if ($assumed) {
            $flags[] = new FlagRaised(RiskFlag::AssumedRepayment, $liability->key, ['months' => $fallbackMonths]);
        }

        $term = $months ?? $fallbackMonths;
        $emi = Amortisation::emi($liability->outstandingPrincipal, $liability->annualInterestRate, $term);
        $schedule = $isPartial
            ? Amortisation::termSchedule($principal, $statedRate, $emi, $assumptions->maxProjectionMonths)
            : Amortisation::termSchedule($principal, $statedRate, $emi, $term);

        return new LiabilityProjection($schedule, $emi, $liability->annualInterestRate, null, $assumed, $flags);
    }

    private function revolving(LiabilityInput $liability, float $principal, int $fallbackMonths, Assumptions $assumptions): LiabilityProjection
    {
        $rate = $liability->annualInterestRate;
        $payment = $liability->monthlyPayment !== null && $liability->monthlyPayment > 0 ? Money::round($liability->monthlyPayment) : null;

        if ($payment === null) {
            $emi = Amortisation::emi($principal, $liability->annualInterestRate, $fallbackMonths);

            return new LiabilityProjection(
                Amortisation::termSchedule($principal, $rate, $emi, $fallbackMonths),
                Money::percentage($principal, $assumptions->revolvingMinimumPaymentPercent),
                $liability->annualInterestRate,
                null,
                true,
                [new FlagRaised(RiskFlag::AssumedRepayment, $liability->key, ['months' => $fallbackMonths])],
            );
        }

        if (! Amortisation::covers($principal, $rate, $payment)) {
            return new LiabilityProjection(
                Amortisation::termSchedule($principal, $rate, $payment, $fallbackMonths),
                $payment,
                $liability->annualInterestRate,
                null,
                true,
                [new FlagRaised(RiskFlag::PaymentBelowInterest, $liability->key, ['payment' => $payment])],
            );
        }

        return new LiabilityProjection(
            Amortisation::termSchedule($principal, $rate, $payment, $assumptions->maxProjectionMonths),
            $payment,
            $liability->annualInterestRate,
        );
    }

    private function interestOnly(LiabilityInput $liability, float $principal, int $fallbackMonths): LiabilityProjection
    {
        $rate = $liability->annualInterestRate;
        $months = $liability->remainingMonths !== null && $liability->remainingMonths > 0 ? $liability->remainingMonths : null;
        $flags = $months === null ? [new FlagRaised(RiskFlag::AssumedRepayment, $liability->key, ['months' => $fallbackMonths])] : [];

        return new LiabilityProjection(
            Amortisation::interestOnlySchedule($principal, $rate, $months ?? $fallbackMonths),
            Amortisation::interest($principal, $rate),
            $liability->annualInterestRate,
            null,
            $months === null,
            $flags,
        );
    }
}
