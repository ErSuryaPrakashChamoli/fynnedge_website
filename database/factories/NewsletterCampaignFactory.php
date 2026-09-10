<?php

namespace Database\Factories;

use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterCampaign>
 */
class NewsletterCampaignFactory extends Factory
{
    protected $model = NewsletterCampaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Monthly insights — '.fake()->monthName(),
            'subject' => fake()->sentence(6),
            'preview_text' => fake()->sentence(10),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'status' => CampaignStatus::Draft,
        ];
    }

    public function scheduled(?\DateTimeInterface $at = null): static
    {
        return $this->state([
            'status' => CampaignStatus::Scheduled,
            'scheduled_at' => $at ?? now()->addHour(),
        ]);
    }

    public function sent(): static
    {
        return $this->state([
            'status' => CampaignStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => CampaignStatus::Cancelled]);
    }
}
