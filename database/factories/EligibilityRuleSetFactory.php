<?php

namespace Database\Factories;

use App\Models\LenderProduct;
use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EligibilityRuleSet>
 */
class EligibilityRuleSetFactory extends Factory
{
    protected $model = EligibilityRuleSet::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'lender_product_id' => LenderProduct::factory(),
            'version' => 1,
            'status' => EligibilityRuleSetStatus::Active,
            'effective_from' => null,
            'effective_until' => null,
            'created_by' => null,
            'notes' => null,
        ];
    }
}
