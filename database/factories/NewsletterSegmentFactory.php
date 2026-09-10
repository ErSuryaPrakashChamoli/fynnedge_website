<?php

namespace Database\Factories;

use App\Modules\Newsletter\Models\NewsletterSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterSegment>
 */
class NewsletterSegmentFactory extends Factory
{
    protected $model = NewsletterSegment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'All active subscribers',
            'description' => fake()->sentence(),
            'criteria' => [],
            'is_active' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function withCriteria(array $criteria): static
    {
        return $this->state(['criteria' => $criteria]);
    }
}
