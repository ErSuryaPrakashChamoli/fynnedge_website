<?php

namespace Database\Factories;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoanProduct>
 */
class LoanProductFactory extends Factory
{
    protected $model = LoanProduct::class;

    public function definition(): array
    {
        $category = $this->faker->randomElement(LoanCategory::cases());

        return [
            'public_id' => Str::uuid(),
            'name' => $category->getLabel(),
            'slug' => Str::slug($category->value).'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'category' => $category,
            'summary' => $this->faker->sentence(12),
            'body' => collect($this->faker->paragraphs(3))->map(fn ($p) => "<p>{$p}</p>")->implode(''),
            'features' => $this->faker->sentences(4),
            'eligibility_points' => $this->faker->sentences(3),
            'documents_required' => ['PAN Card', 'Address Proof', 'Bank Statements (last 3 months)'],
            'process_steps' => ['Check eligibility', 'Compare lenders', 'Apply online', 'Upload documents', 'Get sanctioned'],
            'calculator_key' => null,
            'status' => PublishStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PublishStatus::Published,
            'published_at' => now(),
        ]);
    }
}
