<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\CompanyPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CompanyPhoto>
 */
class CompanyPhotoFactory extends Factory
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
            'photo_path' => 'company-photos/'.$this->faker->uuid().'.jpg',
            'caption' => $this->faker->sentence(4),
            'sort_order' => 0,
            'status' => PublishStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => PublishStatus::Published]);
    }
}
