<?php

namespace Database\Factories;

use App\Models\PageSeo;
use App\Support\UrlPath;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageSeo>
 */
class PageSeoFactory extends Factory
{
    protected $model = PageSeo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'url_path' => UrlPath::normalize('/'.fake()->unique()->slug()),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * The common case: a row that exists only to set the meta title and
     * description for a URL.
     */
    public function withMeta(string $urlPath, ?string $title = null, ?string $description = null): static
    {
        return $this->state(fn (): array => ['url_path' => UrlPath::normalize($urlPath)])
            ->afterCreating(fn (PageSeo $pageSeo) => $pageSeo->seoMeta()->create([
                'title' => $title,
                'description' => $description,
            ]));
    }
}
