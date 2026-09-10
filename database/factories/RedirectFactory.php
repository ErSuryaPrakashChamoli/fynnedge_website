<?php

namespace Database\Factories;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    protected $model = Redirect::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_path' => '/'.fake()->unique()->slug(),
            'destination' => '/'.fake()->slug(),
            'status_code' => 301,
            'is_active' => true,
        ];
    }

    public function temporary(): static
    {
        return $this->state(['status_code' => 302]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
