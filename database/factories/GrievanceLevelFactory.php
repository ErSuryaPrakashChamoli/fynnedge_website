<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\GrievanceLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GrievanceLevel>
 */
class GrievanceLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'level' => 'Level '.$this->faker->numberBetween(1, 3),
            'turnaround_time' => '7 WORKING DAYS',
            'contact_name' => $this->faker->name(),
            'designation' => $this->faker->jobTitle(),
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'sort_order' => 0,
            'status' => PublishStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => PublishStatus::Published]);
    }
}
