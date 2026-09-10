<?php

namespace Database\Factories;

use App\Modules\Newsletter\Models\NewsletterTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterTemplate>
 */
class NewsletterTemplateFactory extends Factory
{
    protected $model = NewsletterTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
