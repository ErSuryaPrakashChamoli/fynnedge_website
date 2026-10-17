<?php

namespace App\Modules\Wallet\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How one credit-score check was paid for (credit_score_checks.billing).
 */
enum CheckBilling: string implements HasLabel
{
    /** The customer's free check (first, or again 6 months after the last free one). */
    case Free = 'free';

    /** Included in an active refresh plan. */
    case Plan = 'plan';

    /** Paid from the wallet (credit_score_checks.wallet_transaction_id). */
    case Wallet = 'wallet';

    /** Answered from a stored score inside the bureau's reuse period: no new pull, no charge. */
    case Reused = 'reused';

    public function getLabel(): string
    {
        return match ($this) {
            self::Free => 'Free check',
            self::Plan => 'Plan',
            self::Wallet => 'Paid from wallet',
            self::Reused => 'Stored score (no charge)',
        };
    }
}
