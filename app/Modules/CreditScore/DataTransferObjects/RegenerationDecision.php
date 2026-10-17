<?php

namespace App\Modules\CreditScore\DataTransferObjects;

use App\Modules\CreditScore\Models\CreditScoreCheck;
use Carbon\CarbonInterface;

/**
 * Whether a credit-score check may ask a bureau for a new report now
 * (CreditReportRegenerationLimit).
 */
final readonly class RegenerationDecision
{
    /** A new report may be generated (or the check needs none: demo, stored score). */
    public const ALLOWED = 'allowed';

    /** Inside the limit, but this bureau's saved score for the same details can be shown again. */
    public const SAVED_SCORE = 'saved_score';

    /** Inside the limit: the customer's own recent reports leave no new one before $nextAt. */
    public const BLOCKED = 'blocked';

    /** Inside the limit because of this PAN's reports on another account; no dates are revealed. */
    public const BLOCKED_FOR_PAN = 'blocked_for_pan';

    public function __construct(
        public string $outcome,
        public ?CarbonInterface $nextAt = null,
        public ?CreditScoreCheck $lastReport = null,
        public ?CarbonInterface $reuseSince = null,
    ) {}

    public function isBlocked(): bool
    {
        return in_array($this->outcome, [self::BLOCKED, self::BLOCKED_FOR_PAN], true);
    }
}
