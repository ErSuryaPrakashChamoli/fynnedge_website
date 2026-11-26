<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

use App\Modules\DebtConsolidation\Services\Finance\Money;

/**
 * The cash needed to close (or pay down) one liability.
 *
 * principal: the part of the outstanding principal being closed.
 * dues: overdue + pending instalment + accrued (broken-period) interest — owed
 * whether or not the customer consolidates. closureCosts: foreclosure
 * charges, the tax on them and other lender charges — the cost of closing.
 */
final readonly class ForeclosureEstimate
{
    public const SOURCE_ESTIMATE = 'estimate';

    public const SOURCE_QUOTATION = 'quotation';

    /**
     * @param  list<FlagRaised>  $flags
     */
    public function __construct(
        public string $liabilityKey,
        public bool $isFullClosure,
        public float $principal,
        public float $overdue,
        public float $pendingInstalment,
        public float $accruedInterest,
        public float $charges,
        public float $tax,
        public float $otherCharges,
        public float $total,
        public string $source = self::SOURCE_ESTIMATE,
        public bool $verified = false,
        public bool $quotationExpired = false,
        public ?string $validUntil = null,
        public array $flags = [],
    ) {}

    public function dues(): float
    {
        return Money::round($this->overdue + $this->pendingInstalment + $this->accruedInterest);
    }

    /**
     * Dues payable immediately in the "keep everything" path. Accrued interest
     * is excluded: without consolidation it is paid inside the next EMI, which
     * the projection already contains.
     */
    public function immediateDues(): float
    {
        return Money::round($this->overdue + $this->pendingInstalment);
    }

    public function closureCosts(): float
    {
        return Money::round($this->charges + $this->tax + $this->otherCharges);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'liability_key' => $this->liabilityKey,
            'is_full_closure' => $this->isFullClosure,
            'principal' => $this->principal,
            'overdue' => $this->overdue,
            'pending_instalment' => $this->pendingInstalment,
            'accrued_interest' => $this->accruedInterest,
            'charges' => $this->charges,
            'tax' => $this->tax,
            'other_charges' => $this->otherCharges,
            'closure_costs' => $this->closureCosts(),
            'total' => $this->total,
            'source' => $this->source,
            'verified' => $this->verified,
            'quotation_expired' => $this->quotationExpired,
            'valid_until' => $this->validUntil,
        ];
    }
}
