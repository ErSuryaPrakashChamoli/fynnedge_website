<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    protected $model = Achievement::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'label' => $this->faker->words(2, true),
            'value' => (string) $this->faker->numberBetween(2, 900),
            'prefix' => null,
            'suffix' => '+',
            'sort_order' => 0,
            'status' => PublishStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => PublishStatus::Published]);
    }
}
