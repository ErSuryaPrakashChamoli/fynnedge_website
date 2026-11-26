<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

use App\Modules\DebtConsolidation\Enums\SuitabilityBand;

/**
 * Financial suitability of one scenario. It says how sensible consolidating
 * looks on the numbers; it never says a lender will lend.
 */
final readonly class SuitabilityResult
{
    /**
     * @param  array<string, array{score: float, weight: float, weighted: float, measure: ?float}>  $dimensions
     */
    public function __construct(
        public float $score,
        public SuitabilityBand $band,
        public array $dimensions,
        public bool $requiresAdvisorReview,
        public string $configVersion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'band' => $this->band->value,
            'dimensions' => $this->dimensions,
            'requires_advisor_review' => $this->requiresAdvisorReview,
            'config_version' => $this->configVersion,
        ];
    }
}
