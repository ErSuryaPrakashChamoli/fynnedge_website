<?php

namespace Database\Factories;

use App\Enums\FaqPlacement;
use App\Enums\PublishStatus;
use App\Enums\SocialPlatform;
use App\Enums\VideoTestimonialSource;
use App\Models\SocialVideo;
use App\Support\SocialVideos\SocialVideos;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SocialVideo>
 */
class SocialVideoFactory extends Factory
{
    protected $model = SocialVideo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform' => SocialPlatform::Instagram,
            'title' => $this->faker->sentence(5),
            'caption' => $this->faker->sentence(12),
            'post_url' => 'https://www.instagram.com/reel/'.Str::random(11).'/',
            'video_source' => VideoTestimonialSource::Upload,
            'video_path' => 'social-videos/'.Str::random(12).'.mp4',
            'youtube_url' => null,
            'poster_path' => null,
            'placements' => [FaqPlacement::Home->value],
            'sort_order' => 0,
            'status' => PublishStatus::Published,
            'published_at' => now(),
        ];
    }

    public function youtube(string $url = 'https://www.youtube.com/shorts/dQw4w9WgXcQ'): static
    {
        return $this->state(fn (): array => [
            'platform' => SocialPlatform::YouTube,
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
        return $this->onPages([SocialVideos::EVERY_PAGE]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => PublishStatus::Draft, 'published_at' => null]);
    }
}
