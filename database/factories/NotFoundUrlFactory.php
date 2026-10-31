<?php

namespace Database\Factories;

use App\Models\NotFoundUrl;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotFoundUrl>
 */
class NotFoundUrlFactory extends Factory
{
    protected $model = NotFoundUrl::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstSeen = fake()->dateTimeBetween('-30 days', '-1 day');

        return [
            'path' => '/'.fake()->unique()->slug(),
            'hits' => fake()->numberBetween(1, 50),
            'internal_referrals' => 0,
            'crawler_hits' => 0,
            'last_referrer' => null,
            'first_seen_at' => $firstSeen,
            'last_seen_at' => fake()->dateTimeBetween($firstSeen, 'now'),
        ];
    }

    /**
     * Requested from a page on this site: a broken internal link.
     */
    public function linkedInternally(): static
    {
        return $this->state(fn (array $attributes): array => [
            'internal_referrals' => max(1, (int) $attributes['hits']),
            'last_referrer' => 'https://fynnedge.com/loans',
        ]);
    }
}
