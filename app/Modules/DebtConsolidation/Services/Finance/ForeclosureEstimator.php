<?php

namespace App\Modules\DebtConsolidation\Services\Finance;

use App\Modules\DebtConsolidation\DataTransferObjects\Assumptions;
use App\Modules\DebtConsolidation\DataTransferObjects\FlagRaised;
use App\Modules\DebtConsolidation\DataTransferObjects\ForeclosureEstimate;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Enums\RiskFlag;

/**
 * Estimates the closure amount of a liability: principal closed + dues +
 * foreclosure charge (percent of the amount closed plus any fixed fee) + GST
 * on that charge + other lender charges. For a full closure a lender's
 * quotation replaces the estimate; it is "verified" only once staff verified
 * it against the lender's letter, and stops counting as current after its
 * validity date.
 */
final class ForeclosureEstimator
{
    public function estimate(LiabilityInput $liability, Assumptions $assumptions): ?ForeclosureEstimate
    {
        if ($liability->mode === ConsolidationMode::Exclude) {
            return null;
        }

        $outstanding = Money::round($liability->outstandingPrincipal);
        $closing = $liability->mode === ConsolidationMode::Full
            ? $outstanding
            : Money::round(min(max(0.0, (float) $liability->partialAmount), $outstanding));

        if ($closing <= 0) {
            return null;
        }

        $isFull = $closing >= $outstanding;
        $overdue = Money::round(max(0.0, $liability->overdueAmount));
        $pending = Money::round(max(0.0, $liability->pendingInstalment));
        $accrued = $isFull ? Money::round(max(0.0, $liability->accruedInterest)) : 0.0;
        $charges = Money::round(Money::percentage($closing, max(0.0, $liability->foreclosureChargePercent)) + max(0.0, $liability->foreclosureChargeFixed));
        $tax = Money::percentage($charges, $assumptions->gstRatePercent);
        $other = Money::round(max(0.0, $liability->otherCharges));
        $flags = [];

        if ($liability->lockInEndsOn !== null && $liability->lockInEndsOn > $assumptions->asOf) {
            $flags[] = new FlagRaised(RiskFlag::ForeclosureLockIn, $liability->key, ['until' => $liability->lockInEndsOn]);
        }

        if ($isFull && $liability->quotedClosureAmount !== null && $liability->quotedClosureAmount > 0) {
            $quoted = Money::round($liability->quotedClosureAmount);
            $expired = $liability->quotationValidUntil !== null && $liability->quotationValidUntil < $assumptions->asOf;
            $costs = Money::round(max(0.0, $quoted - $closing - $overdue - $pending - $accrued));

            if ($expired) {
                $flags[] = new FlagRaised(RiskFlag::QuotationExpired, $liability->key, ['valid_until' => $liability->quotationValidUntil]);
            } elseif (! $liability->quotationVerified) {
                $flags[] = new FlagRaised(RiskFlag::ForeclosureUnverified, $liability->key);
            }

            return new ForeclosureEstimate(
                liabilityKey: $liability->key,
                isFullClosure: true,
                principal: $closing,
                overdue: $overdue,
                pendingInstalment: $pending,
                accruedInterest: $accrued,
                charges: $costs,
                tax: 0.0,
                otherCharges: 0.0,
                total: $quoted,
                source: ForeclosureEstimate::SOURCE_QUOTATION,
                verified: $liability->quotationVerified && ! $expired,
                quotationExpired: $expired,
                validUntil: $liability->quotationValidUntil,
                flags: $flags,
            );
        }

        $flags[] = new FlagRaised(RiskFlag::ForeclosureUnverified, $liability->key);

        return new ForeclosureEstimate(
            liabilityKey: $liability->key,
            isFullClosure: $isFull,
            principal: $closing,
            overdue: $overdue,
            pendingInstalment: $pending,
            accruedInterest: $accrued,
            charges: $charges,
            tax: $tax,
            otherCharges: $other,
            total: Money::round($closing + $overdue + $pending + $accrued + $charges + $tax + $other),
            flags: $flags,
        );
    }
}
