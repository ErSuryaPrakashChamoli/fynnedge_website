<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\HowItWorksStep;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HowItWorksStep>
 */
class HowItWorksStepFactory extends Factory
{
    protected $model = HowItWorksStep::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(10),
            'sort_order' => 0,
            'status' => PublishStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => PublishStatus::Published]);
    }
}
