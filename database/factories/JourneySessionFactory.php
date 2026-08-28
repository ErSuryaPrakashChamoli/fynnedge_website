<?php

namespace Database\Factories;

use App\Models\LoanProduct;
use App\Modules\Journey\Enums\JourneySessionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneySession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JourneySession>
 */
class JourneySessionFactory extends Factory
{
    protected $model = JourneySession::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'loan_product_id' => LoanProduct::factory(),
            'journey_definition_id' => JourneyDefinition::factory(),
            'current_step_id' => null,
            'status' => JourneySessionStatus::InProgress,
        ];
    }
}
