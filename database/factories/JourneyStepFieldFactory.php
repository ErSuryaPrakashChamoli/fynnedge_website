<?php

namespace Database\Factories;

use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JourneyStepField>
 */
class JourneyStepFieldFactory extends Factory
{
    protected $model = JourneyStepField::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'journey_step_id' => JourneyStep::factory(),
            'key' => $this->faker->unique()->word(),
            'label' => $this->faker->words(2, true),
            'type' => FieldType::Text,
            'options' => null,
            'validation_rules' => ['required'],
            'help_text' => null,
            'order' => 0,
            'conditional_on' => null,
        ];
    }
}
