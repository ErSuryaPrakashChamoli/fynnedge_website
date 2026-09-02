<?php

namespace Database\Factories;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(6);

        return [
            'public_id' => Str::uuid(),
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => $this->faker->sentence(),
            'body' => collect($this->faker->paragraphs(3))->map(fn ($p) => "<p>{$p}</p>")->implode(''),
            'category' => null,
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

    public function forCategory(LoanCategory $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }
}
