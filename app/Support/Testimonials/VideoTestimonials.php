<?php

namespace App\Support\Testimonials;

use App\Models\VideoTestimonial;
use App\Support\Faqs\FaqPlacements;
use Illuminate\Support\Collection;

/**
 * Resolves the video testimonials for the page being rendered.
 *
 * Placement reuses the FAQ vocabulary (route names, optionally narrowed to
 * one page — see FaqPlacements) so admins pick pages from the same list in
 * both places, plus one extra token, EVERY_PAGE, for site-wide videos.
 *
 * The result is memoised on the current request because up to three
 * components ask for it on one page: the section, the floating bubble and
 * the player modal. Request attributes, rather than a static property, keep
 * that cache from leaking between requests in long-lived workers and tests.
 *
 * Never call this from inside a Livewire component: Livewire re-renders
 * arrive on the `livewire.update` route, so page-specific videos would vanish.
 */
class VideoTestimonials
{
    public const EVERY_PAGE = 'all';

    private const REQUEST_KEY = 'video_testimonials.current_page';

    /**
     * @return Collection<int, VideoTestimonial>
     */
    public static function forCurrentPage(): Collection
    {
        $attributes = request()->attributes;

        if (! $attributes->has(self::REQUEST_KEY)) {
            $attributes->set(self::REQUEST_KEY, VideoTestimonial::query()
                ->published()
                ->forPlacements([self::EVERY_PAGE, ...FaqPlacements::currentTokens()])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->filter(fn (VideoTestimonial $testimonial): bool => $testimonial->isPlayable())
                ->values());
        }

        return $attributes->get(self::REQUEST_KEY);
    }

    /**
     * The first video on this page an admin marked to float in the corner.
     */
    public static function floatingForCurrentPage(): ?VideoTestimonial
    {
        return self::forCurrentPage()->first(fn (VideoTestimonial $testimonial): bool => $testimonial->show_as_floating);
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
