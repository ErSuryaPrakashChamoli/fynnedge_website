<?php

namespace App\Support\SocialVideos;

use App\Enums\SocialPlatform;
use App\Models\MarketingSection;
use App\Models\Setting;
use App\Models\SocialVideo;
use App\Support\Faqs\FaqPlacements;
use Illuminate\Support\Collection;

/**
 * Resolves the social media videos for the page being rendered, and the
 * wording around them.
 *
 * Placement works exactly like video testimonials: the FAQ vocabulary (route
 * names, optionally narrowed to one page — see FaqPlacements) plus
 * EVERY_PAGE. The result is memoised on the current request because both the
 * section and the shared player modal ask for it. Never call this from inside
 * a Livewire component: Livewire re-renders arrive on the `livewire.update`
 * route, so page-specific videos would vanish.
 *
 * The section's eyebrow, heading, intro and optional button come from the
 * MarketingSection published for SECTION_PLACEMENT (Admin → Content →
 * Marketing sections), falling back to the built-in wording below, the same
 * additive contract every other MarketingSection placement follows.
 */
class SocialVideos
{
    public const EVERY_PAGE = 'all';

    public const SECTION_PLACEMENT = 'social_videos';

    private const REQUEST_KEY = 'social_videos.current_page';

    /**
     * @return Collection<int, SocialVideo>
     */
    public static function forCurrentPage(): Collection
    {
        $attributes = request()->attributes;

        if (! $attributes->has(self::REQUEST_KEY)) {
            $attributes->set(self::REQUEST_KEY, SocialVideo::query()
                ->published()
                ->forPlacements([self::EVERY_PAGE, ...FaqPlacements::currentTokens()])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->filter(fn (SocialVideo $video): bool => $video->isPlayable())
                ->values());
        }

        return $attributes->get(self::REQUEST_KEY);
    }

    /**
     * The section's wording. A blank admin field falls back to the default
     * rather than rendering empty, so clearing a field restores it.
     *
     * @return array{eyebrow: string, heading: string, intro: string, ctaLabel: ?string, ctaUrl: ?string}
     */
    public static function sectionCopy(): array
    {
        $section = MarketingSection::forPlacement(self::SECTION_PLACEMENT);
        $ctaUrl = filled($section?->cta_url) && preg_match('#^(https?://|/)#i', $section->cta_url) === 1 ? $section->cta_url : null;

        return [
            'eyebrow' => filled($section?->subheading) ? $section->subheading : __('As seen on social'),
            'heading' => filled($section?->heading) ? $section->heading : __('Loan clarity, one reel at a time.'),
            'intro' => filled($section?->description) ? $section->description : __('Bite-sized loan tips, straight from our Instagram, Facebook and LinkedIn.'),
            'ctaLabel' => $ctaUrl ? ($section->cta_label ?: __('Follow us')) : null,
            'ctaUrl' => $ctaUrl,
        ];
    }

    /**
     * FynnEdge's profile on each network that has one set under Admin →
     * Settings → Social media, for the section's follow buttons.
     *
     * @return array<string, array{label: string, url: string, icon: string}>
     */
    public static function followLinks(): array
    {
        $links = [];

        foreach (SocialPlatform::cases() as $platform) {
            $url = Setting::get($platform->profileSettingKey());

            if (filled($url) && preg_match('#^https?://#i', (string) $url) === 1) {
                $links[$platform->value] = ['label' => $platform->getLabel(), 'url' => (string) $url, 'icon' => $platform->iconPath()];
            }
        }

        return $links;
    }

    /**
     * Grouped options for the "Show on these pages" Select.
     *
     * @return array<string, array<string, string>>
     */
    public static function options(): array
    {
        return [
            'Site-wide' => [self::EVERY_PAGE => 'Every page on the website'],
            ...FaqPlacements::options(),
        ];
    }

    public static function label(string $token): string
    {
        return $token === self::EVERY_PAGE ? 'Every page' : FaqPlacements::label($token);
    }
}
