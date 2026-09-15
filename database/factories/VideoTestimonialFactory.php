<?php

namespace Database\Factories;

use App\Enums\FaqPlacement;
use App\Enums\PublishStatus;
use App\Enums\VideoTestimonialSource;
use App\Models\VideoTestimonial;
use App\Support\Testimonials\VideoTestimonials;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VideoTestimonial>
 */
class VideoTestimonialFactory extends Factory
{
    protected $model = VideoTestimonial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_name' => $this->faker->name(),
            'role_location' => $this->faker->city(),
            'customer_photo_path' => null,
            'loan_category' => null,
            'rating' => $this->faker->numberBetween(4, 5),
            'headline' => $this->faker->sentence(5),
            'quote' => $this->faker->sentence(12),
            'video_source' => VideoTestimonialSource::Upload,
            'video_path' => 'video-testimonials/'.Str::random(12).'.mp4',
            'youtube_url' => null,
            'poster_path' => null,
            'placements' => [FaqPlacement::Home->value],
            'show_as_floating' => false,
            'sort_order' => 0,
            'status' => PublishStatus::Published,
            'published_at' => now(),
        ];
    }

    public function youtube(string $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'): static
    {
        return $this->state(fn (): array => [
            'video_source' => VideoTestimonialSource::YouTube,
            'video_path' => null,
            'youtube_url' => $url,
        ]);
    }

    /**
     * @param  array<int, string>  $placements
     */
    public function onPages(array $placements): static
    {
        return $this->state(fn (): array => ['placements' => $placements]);
    }

    public function onEveryPage(): static
    {
        return $this->onPages([VideoTestimonials::EVERY_PAGE]);
    }

    public function floating(): static
    {
        return $this->state(fn (): array => ['show_as_floating' => true]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => PublishStatus::Draft, 'published_at' => null]);
    }
}
