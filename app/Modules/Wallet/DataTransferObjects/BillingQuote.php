<?php

namespace App\Modules\Wallet\DataTransferObjects;

use App\Modules\Wallet\Enums\CheckBilling;
use App\Modules\Wallet\Models\CustomerPlan;
use Carbon\CarbonInterface;

/**
 * What a new credit-score check would cost this customer right now.
 * `mode` null means billing does not apply (switched off, or the bureau's
 * page is not live so no real pull happens).
 */
final readonly class BillingQuote
{
    public function __construct(
        public ?CheckBilling $mode,
        public ?Price $price = null,
        public int $balancePaise = 0,
        public ?CarbonInterface $freeAgainAt = null,
        public bool $firstPaidRefresh = false,
        public ?CustomerPlan $plan = null,
    ) {}

    public function isCharged(): bool
    {
        return $this->mode === CheckBilling::Wallet;
    }

    /** A paid refresh the wallet cannot cover: the check must not run. */
    public function needsTopUp(): bool
    {
        return $this->isCharged() && $this->balancePaise < $this->price->totalPaise();
    }
}
