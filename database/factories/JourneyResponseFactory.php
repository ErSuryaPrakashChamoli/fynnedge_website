<?php

namespace Database\Factories;

use App\Modules\Journey\Models\JourneyResponse;
use App\Modules\Journey\Models\JourneySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JourneyResponse>
 */
class JourneyResponseFactory extends Factory
{
    protected $model = JourneyResponse::class;

    public function definition(): array
    {
        return [
            'journey_session_id' => JourneySession::factory(),
            'field_key' => $this->faker->unique()->word(),
            'value' => $this->faker->word(),
        ];
    }
}
