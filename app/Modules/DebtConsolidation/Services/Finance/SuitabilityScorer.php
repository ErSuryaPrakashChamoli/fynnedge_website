<?php

namespace App\Modules\DebtConsolidation\Services\Finance;

use App\Modules\DebtConsolidation\DataTransferObjects\AffordabilityResult;
use App\Modules\DebtConsolidation\DataTransferObjects\FlagRaised;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioResult;
use App\Modules\DebtConsolidation\DataTransferObjects\ScoringConfig;
use App\Modules\DebtConsolidation\DataTransferObjects\SuitabilityResult;
use App\Modules\DebtConsolidation\Enums\SuitabilityBand;

/**
 * Consolidation Suitability Score (0–100) = Σ weight × dimension score / 100.
 * Every dimension scores 0–100 on a straight line between two thresholds:
 *
 *  financial_benefit    net benefit as % of existing remaining repayment:
 *                       0 at ≤ 0 %, 100 at ≥ benefit_full_percent
 *  affordability        individual: proposed FOIR, 100 at ≤ foir_best, 0 at ≥ foir_worst
 *                       business:   proposed DSCR, 100 at ≥ dscr_best, 0 at ≤ dscr_worst
 *                       unknown income/cash flow scores 0
 *  interest_improvement weighted existing rate − proposed rate (points):
 *                       0 at ≤ 0, 100 at ≥ rate_full_points
 *  cost_efficiency      all consolidation costs as % of the new loan:
 *                       100 at ≤ cost_best_percent, 0 at ≥ cost_worst_percent
 *  repayment_profile    extra months of repayment: 100 at ≤ 0, 0 at ≥ tenure_worst_months
 *
 * Band: ≥ band_potentially_suitable → Potentially suitable; ≥ band_needs_review
 * → Needs review; otherwise Low suitability. A scenario with no net benefit is
 * always "Not financially beneficial", whatever its score.
 */
final class SuitabilityScorer
{
    /**
     * @param  list<FlagRaised>  $flags  every flag raised for the case and scenario
     */
    public function score(ScenarioResult $scenario, AffordabilityResult $affordability, ScoringConfig $config, array $flags): SuitabilityResult
    {
        $benefitPercent = $scenario->benefitPercent ?? 0.0;
        $costPercent = Money::percentOf($scenario->totalConsolidationCosts, $scenario->loanAmount) ?? 0.0;

        $measures = [
            'financial_benefit' => [$benefitPercent, $this->rising($benefitPercent, 0.0, $config->threshold('benefit_full_percent'))],
            'affordability' => [$affordability->proposedValue, $this->affordabilityScore($affordability, $config)],
            'interest_improvement' => [$scenario->rateImprovementPoints, $this->rising($scenario->rateImprovementPoints, 0.0, $config->threshold('rate_full_points'))],
            'cost_efficiency' => [$costPercent, $this->falling($costPercent, $config->threshold('cost_best_percent'), $config->threshold('cost_worst_percent'))],
            'repayment_profile' => [(float) $scenario->tenureChangeMonths, $this->falling((float) $scenario->tenureChangeMonths, 0.0, $config->threshold('tenure_worst_months'))],
        ];

        $dimensions = [];
        $total = 0.0;

        foreach ($measures as $dimension => [$measure, $score]) {
            $weight = $config->weight($dimension);
            $weighted = round($score * $weight / 100, 2);
            $total += $weighted;
            $dimensions[$dimension] = [
                'score' => round($score, 2),
                'weight' => $weight,
                'weighted' => $weighted,
                'measure' => $measure === null ? null : round($measure, 2),
            ];
        }

        $total = round($total, 2);

        $band = match (true) {
            ! $scenario->isBeneficial() => SuitabilityBand::NotBeneficial,
            $total >= $config->threshold('band_potentially_suitable') => SuitabilityBand::PotentiallySuitable,
            $total >= $config->threshold('band_needs_review') => SuitabilityBand::NeedsReview,
            default => SuitabilityBand::LowSuitability,
        };

        $needsReview = $band !== SuitabilityBand::PotentiallySuitable
            || array_filter($flags, fn (FlagRaised $flag): bool => $flag->flag->requiresAdvisorReview()) !== [];

        return new SuitabilityResult($total, $band, $dimensions, $needsReview, $config->version);
    }

    private function affordabilityScore(AffordabilityResult $affordability, ScoringConfig $config): float
    {
        if ($affordability->metric === 'foir') {
            return $affordability->proposedValue === null
                ? 0.0
                : $this->falling($affordability->proposedValue, $config->threshold('foir_best'), $config->threshold('foir_worst'));
        }

        if ($affordability->income === null) {
            return 0.0;
        }

        return $affordability->proposedValue === null
            ? 100.0
            : $this->rising($affordability->proposedValue, $config->threshold('dscr_worst'), $config->threshold('dscr_best'));
    }

    /**
     * 0 at or below $zeroAt, 100 at or above $fullAt.
     */
    private function rising(float $value, float $zeroAt, float $fullAt): float
    {
        if ($value <= $zeroAt) {
            return 0.0;
        }

        if ($value >= $fullAt) {
            return 100.0;
        }

        return ($value - $zeroAt) / ($fullAt - $zeroAt) * 100;
    }

    /**
     * 100 at or below $fullAt, 0 at or above $zeroAt.
     */
    private function falling(float $value, float $fullAt, float $zeroAt): float
    {
        if ($value <= $fullAt) {
            return 100.0;
        }

        if ($value >= $zeroAt) {
            return 0.0;
        }

        return ($zeroAt - $value) / ($zeroAt - $fullAt) * 100;
    }
}
