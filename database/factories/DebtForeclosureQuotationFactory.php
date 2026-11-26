<?php

namespace Database\Factories;

use App\Modules\DebtConsolidation\Enums\VerificationStatus;
use App\Modules\DebtConsolidation\Models\DebtForeclosureQuotation;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtForeclosureQuotation>
 */
class DebtForeclosureQuotationFactory extends Factory
{
    protected $model = DebtForeclosureQuotation::class;

    public function definition(): array
    {
        return [
            'debt_liability_id' => DebtLiability::factory(),
            'quoted_on' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'total_amount' => 515000,
            'verification_status' => VerificationStatus::Unverified,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => ['verification_status' => VerificationStatus::Verified, 'verified_at' => now()]);
    }
}
