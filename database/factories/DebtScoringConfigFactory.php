<?php

namespace Database\Factories;

use App\Modules\DebtConsolidation\DataTransferObjects\ScoringConfig;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\LenderPolicyStatus;
use App\Modules\DebtConsolidation\Models\DebtScoringConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtScoringConfig>
 */
class DebtScoringConfigFactory extends Factory
{
    protected $model = DebtScoringConfig::class;

    public function definition(): array
    {
        $defaults = ScoringConfig::defaults(ApplicantType::Individual);

        return [
            'applicant_type' => ApplicantType::Individual,
            'version' => 'v'.$this->faker->unique()->numberBetween(2, 9999),
            'status' => LenderPolicyStatus::Draft,
            'weights' => $defaults->weights,
            'thresholds' => $defaults->thresholds,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => LenderPolicyStatus::Active, 'activated_at' => now()]);
    }
}
