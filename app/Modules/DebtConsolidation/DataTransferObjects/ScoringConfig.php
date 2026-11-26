<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

use App\Modules\DebtConsolidation\Enums\ApplicantType;
use InvalidArgumentException;

/**
 * Suitability weights and thresholds for one applicant type. Stored and
 * versioned in debt_scoring_configs; defaults() is the baseline shipped with
 * formula version 1 (weights from the project mandate, thresholds documented
 * in docs/debt-consolidation/06-financial-engine.md and pending business
 * sign-off).
 */
final readonly class ScoringConfig
{
    public const DIMENSIONS = ['financial_benefit', 'affordability', 'interest_improvement', 'cost_efficiency', 'repayment_profile'];

    /**
     * @param  array<string, float>  $weights  dimension => percent, summing to 100
     * @param  array<string, float>  $thresholds
     */
    public function __construct(
        public ApplicantType $applicantType,
        public string $version,
        public array $weights,
        public array $thresholds,
    ) {
        $this->validate();
    }

    public static function defaults(ApplicantType $applicantType): self
    {
        return new self(
            applicantType: $applicantType,
            version: 'default-1',
            weights: [
                'financial_benefit' => 30.0,
                'affordability' => 25.0,
                'interest_improvement' => 20.0,
                'cost_efficiency' => 15.0,
                'repayment_profile' => 10.0,
            ],
            thresholds: self::defaultThresholds(),
        );
    }

    /**
     * @return array<string, float>
     */
    public static function defaultThresholds(): array
    {
        return [
            // Net benefit as a % of the existing remaining repayment that earns full marks.
            'benefit_full_percent' => 10.0,
            // Interest rate reduction (percentage points) that earns full marks.
            'rate_full_points' => 8.0,
            // Consolidation costs as % of the new loan: full marks at or below best, zero at or above worst.
            'cost_best_percent' => 1.0,
            'cost_worst_percent' => 6.0,
            // Extra months of repayment that score zero.
            'tenure_worst_months' => 36.0,
            // Individuals: proposed FOIR % bands and scoring range.
            'foir_comfortable' => 40.0,
            'foir_manageable' => 50.0,
            'foir_stretched' => 65.0,
            'foir_best' => 40.0,
            'foir_worst' => 70.0,
            // Businesses: proposed DSCR bands and scoring range.
            'dscr_comfortable' => 1.75,
            'dscr_manageable' => 1.4,
            'dscr_stretched' => 1.2,
            'dscr_best' => 1.75,
            'dscr_worst' => 1.0,
            // Total score bands.
            'band_potentially_suitable' => 70.0,
            'band_needs_review' => 50.0,
        ];
    }

    public function threshold(string $key): float
    {
        return (float) ($this->thresholds[$key] ?? self::defaultThresholds()[$key]);
    }

    public function weight(string $dimension): float
    {
        return (float) ($this->weights[$dimension] ?? 0.0);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            applicantType: ApplicantType::from($data['applicant_type']),
            version: (string) $data['version'],
            weights: array_map('floatval', (array) $data['weights']),
            thresholds: array_map('floatval', (array) $data['thresholds']),
        );
    }

    /**
     * @return array{applicant_type: string, version: string, weights: array<string, float>, thresholds: array<string, float>}
     */
    public function toArray(): array
    {
        return [
            'applicant_type' => $this->applicantType->value,
            'version' => $this->version,
            'weights' => $this->weights,
            'thresholds' => [...self::defaultThresholds(), ...$this->thresholds],
        ];
    }

    /**
     * @return list<string>
     */
    public static function problemsWith(array $weights, array $thresholds): array
    {
        $problems = [];

        foreach (self::DIMENSIONS as $dimension) {
            if (! isset($weights[$dimension]) || (float) $weights[$dimension] < 0) {
                $problems[] = "Weight for {$dimension} is missing or negative.";
            }
        }

        if (abs(array_sum(array_map('floatval', $weights)) - 100.0) > 0.001) {
            $problems[] = 'Weights must add up to 100.';
        }

        $merged = [...self::defaultThresholds(), ...array_map('floatval', $thresholds)];

        if ($merged['cost_best_percent'] >= $merged['cost_worst_percent']) {
            $problems[] = 'Cost "best" must be lower than cost "worst".';
        }

        if ($merged['foir_best'] >= $merged['foir_worst']) {
            $problems[] = 'FOIR "best" must be lower than FOIR "worst".';
        }

        if ($merged['dscr_best'] <= $merged['dscr_worst']) {
            $problems[] = 'DSCR "best" must be higher than DSCR "worst".';
        }

        if (! ($merged['foir_comfortable'] <= $merged['foir_manageable'] && $merged['foir_manageable'] <= $merged['foir_stretched'])) {
            $problems[] = 'FOIR bands must increase: comfortable ≤ manageable ≤ stretched.';
        }

        if (! ($merged['dscr_comfortable'] >= $merged['dscr_manageable'] && $merged['dscr_manageable'] >= $merged['dscr_stretched'])) {
            $problems[] = 'DSCR bands must decrease: comfortable ≥ manageable ≥ stretched.';
        }

        if ($merged['band_needs_review'] >= $merged['band_potentially_suitable']) {
            $problems[] = 'The "needs review" band must start below the "potentially suitable" band.';
        }

        foreach (['benefit_full_percent', 'rate_full_points', 'tenure_worst_months'] as $positive) {
            if ($merged[$positive] <= 0) {
                $problems[] = "{$positive} must be greater than zero.";
            }
        }

        return $problems;
    }

    private function validate(): void
    {
        $problems = self::problemsWith($this->weights, $this->thresholds);

        if ($problems !== []) {
            throw new InvalidArgumentException(implode(' ', $problems));
        }
    }
}
