<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
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
            'image_path' => 'banners/'.$this->faker->uuid().'.jpg',
            'heading' => $this->faker->sentence(4),
            'subtitle' => $this->faker->sentence(8),
            'cta_label' => 'Learn more',
            'cta_url' => '/',
            'sort_order' => 0,
            'status' => PublishStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => PublishStatus::Published]);
    }
}
