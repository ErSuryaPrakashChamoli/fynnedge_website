<?php

namespace Database\Factories;

use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneyStep;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JourneyStep>
 */
class JourneyStepFactory extends Factory
{
    protected $model = JourneyStep::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'journey_definition_id' => JourneyDefinition::factory(),
            'key' => $this->faker->unique()->slug(2),
            'title' => $this->faker->words(3, true),
            'description' => null,
            'order' => 0,
            'condition_rules' => null,
        ];
    }
}
