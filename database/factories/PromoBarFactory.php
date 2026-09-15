<?php

namespace Database\Factories;

use App\Enums\FaqPlacement;
use App\Enums\PromoBarDevice;
use App\Enums\PromoBarTrigger;
use App\Enums\PublishStatus;
use App\Models\PromoBar;
use App\Support\PromoBars\PromoBars;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoBar>
 */
class PromoBarFactory extends Factory
{
    protected $model = PromoBar::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'eyebrow' => 'Limited time offer',
            'headline' => $this->faker->sentence(6),
            'rotating_messages' => null,
            'cta_label' => 'Apply now',
            'cta_url' => '/eligibility',
            'cta_opens_new_tab' => false,
            'image_path' => null,
            'placements' => [FaqPlacement::Home->value],
            'excluded_placements' => null,
            'trigger' => PromoBarTrigger::Scroll,
            'trigger_value' => 25,
            'device' => PromoBarDevice::All,
            'reshow_after_hours' => 24,
            'show_countdown' => false,
            'sort_order' => 0,
            'status' => PublishStatus::Published,
            'published_at' => now(),
        ];
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
        return $this->onPages([PromoBars::EVERY_PAGE]);
    }

    /**
     * @param  array<int, string>  $placements
     */
    public function hiddenOn(array $placements): static
    {
        return $this->state(fn (): array => ['excluded_placements' => $placements]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => PublishStatus::Draft, 'published_at' => null]);
    }
}
