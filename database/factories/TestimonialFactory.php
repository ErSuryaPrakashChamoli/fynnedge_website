<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'customer_name' => $this->faker->name(),
            'role_location' => $this->faker->city(),
            'loan_category' => null,
            'rating' => $this->faker->numberBetween(4, 5),
            'quote' => $this->faker->paragraph(),
            'avatar_path' => null,
            'sort_order' => 0,
            'status' => PublishStatus::Published,
            'published_at' => now(),
        ];
    }
}
