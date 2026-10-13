<?php

namespace Database\Factories;

use App\Enums\LandingPageGroup;
use App\Enums\PublishStatus;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoanLandingPage>
 */
class LoanLandingPageFactory extends Factory
{
    protected $model = LoanLandingPage::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(4);
        $group = $this->faker->randomElement(LandingPageGroup::cases());

        return [
            'public_id' => Str::uuid(),
            'loan_product_id' => LoanProduct::factory(),
            'group' => $group,
            'title' => $title,
            'slug' => Str::slug($title),
            'amount' => null,
            'city' => $group === LandingPageGroup::ByLocation ? $this->faker->city() : null,
            'excerpt' => $this->faker->sentence(),
            'body' => collect($this->faker->paragraphs(2))->map(fn ($p) => "<p>{$p}</p>")->implode(''),
            'sort_order' => 0,
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

    public function inCity(string $city): static
    {
        return $this->state(fn () => [
            'group' => LandingPageGroup::ByLocation,
            'city' => $city,
        ]);
    }
}
