<?php

namespace App\Modules\Customers\DataTransferObjects;

use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use Carbon\CarbonInterface;

/**
 * Whether a customer may ask one bureau for a new score now, and why not.
 *
 * Two rules decide it: the bureau's existing public-score reuse period (30
 * days by default) and the regeneration limit — one new report every 3
 * months, at most 4 a year, per customer and PAN across all bureaus
 * (CreditReportRegenerationLimit). A refresh inside either would only show
 * the saved score, so the portal offers none.
 */
final readonly class RefreshEligibility
{
    public const AVAILABLE = 'available';

    /** The reuse period or the regeneration limit has not passed yet. */
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
