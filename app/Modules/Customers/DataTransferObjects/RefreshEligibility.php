<?php

namespace App\Modules\Customers\DataTransferObjects;

use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use Carbon\CarbonInterface;

/**
 * Whether a customer may ask one bureau for a new score now, and why not.
 *
 * The period is the bureau's existing public-score reuse period (30 days by
 * default, each editable in its bureau's Settings page) — the same rule that
 * already stops the public page paying twice. A refresh inside it would only
 * be answered from the stored score, so the portal offers none.
 */
final readonly class RefreshEligibility
{
    public const AVAILABLE = 'available';

    /** The last successful report is younger than the reuse period. */
    public const TOO_SOON = 'too_soon';

    /** A check is still waiting for its outcome; another could pay twice. */
    public const IN_PROGRESS = 'in_progress';

    /** The bureau's public page is not switched to real scores. */
    public const UNAVAILABLE = 'unavailable';

    public function __construct(
        public BureauName $bureau,
        public string $state,
        public int $reuseDays,
        public ?CreditScoreCheck $latestSuccess,
        public ?CreditScoreCheck $latestAttempt,
        public ?CarbonInterface $nextEligibleAt,
    ) {}

    public function isAvailable(): bool
    {
        return $this->state === self::AVAILABLE;
    }
}
