<?php

namespace Database\Factories;

use App\Models\LenderProduct;
use App\Modules\Eligibility\Enums\EligibilityStatus;
use App\Modules\Eligibility\Models\EligibilityResult;
use App\Modules\Journey\Models\JourneySession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EligibilityResult>
 */
class EligibilityResultFactory extends Factory
{
    protected $model = EligibilityResult::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'journey_session_id' => JourneySession::factory(),
            'lender_product_id' => LenderProduct::factory(),
            'eligibility_rule_set_id' => null,
            'status' => EligibilityStatus::Eligible,
            'foir' => null,
            'evaluated_at' => now(),
        ];
    }
}
