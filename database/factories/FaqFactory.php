<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'question' => $this->faker->sentence().'?',
            'answer' => $this->faker->paragraph(),
            'sort_order' => 0,
            'status' => PublishStatus::Published,
        ];
    }
}
