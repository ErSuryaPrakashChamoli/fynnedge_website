<?php

namespace Database\Factories;

use App\Models\LoanProduct;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JourneyDefinition>
 */
class JourneyDefinitionFactory extends Factory
{
    protected $model = JourneyDefinition::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'loan_product_id' => LoanProduct::factory(),
            'version' => 1,
            'status' => JourneyDefinitionStatus::Active,
        ];
    }
}
