<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\MarketingSection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketingSection>
 */
class MarketingSectionFactory extends Factory
{
    protected $model = MarketingSection::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'placement' => 'home_finance_cta',
            'heading' => $this->faker->sentence(4),
            'subheading' => null,
            'description' => $this->faker->sentence(12),
            'image_path' => null,
            'cta_label' => 'Check Your Eligibility',
            'cta_url' => '/eligibility',
            'sort_order' => 0,
            'status' => PublishStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => PublishStatus::Published]);
    }
}
