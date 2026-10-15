<?php

namespace App\Support\Pages;

use App\Models\Setting;
use App\Support\Localization\Phrasebook;
use App\Support\Localization\SiteLocale;

/**
 * Everything on the /about page an admin can reword (Admin → Website
 * Settings → About Page), kept in ONE Setting so the page is saved, cached
 * and reset as a unit.
 *
 * Same additive contract as QuickEnquiryPageContent: nothing saved means the
 * page reads exactly as built, and a blank text field falls back to its
 * default rather than rendering empty. Lists differ on purpose — a list that
 * was never saved shows the defaults, but a saved empty list means the admin
 * removed every item, so that block is hidden.
 *
 * Hindi visitors get the admin's Hindi wording (a second Setting,
 * `about_page_hi`, same shape), and every field left blank there is the
 * English translated through lang/hi.json — so a page nobody has touched is
 * fully Hindi. See Phrasebook::localize().
 *
 * Not here by design: the page title, intro and rich-text body (Content →
 * Pages → "about"), the founder's name and photo (Settings → Founder message),
 * the "Life at FynnEdge" photos (CompanyPhoto) and the shared "Why FynnEdge"
 * cards, which also appear on the loan pages.
 */
class AboutPageContent
{
    public const SETTING_KEY = 'about_page';

    public const HINDI_SETTING_KEY = self::SETTING_KEY.'_hi';

    /**
     * A list item missing any of these is dropped rather than rendered half-empty.
     */
    private const REQUIRED_ITEM_KEYS = [
        'founder_points' => ['text'],
        'values' => ['title'],
    ];

    /**
     * @return array{
     *     founder_heading: string,
     *     founder_heading_accent: string,
     *     founder_points: array<int, array{text: string}>,
     *     founder_quote: string,
     *     founder_role: string,
     *     mission_label: string,
     *     mission_title: string,
     *     mission_body: string,
     *     vision_label: string,
     *     vision_title: string,
     *     vision_body: string,
     *     life_eyebrow: string,
     *     life_heading: string,
     *     life_description: string,
     *     values: array<int, array{title: string, body: string}>,
     *     work_heading: string,
     *     work_description: string,
     *     work_button_label: string,
     * }
     */
    public static function defaults(): array
    {
        return [
            'founder_heading' => 'FynnEdge began with a question: what if getting a loan could feel',
            'founder_heading_accent' => 'simpler, smarter, and more human?',
            'founder_points' => [
                ['text' => 'We built FynnEdge to redefine the lending experience — bringing clarity to complexity, technology to convenience, and trust to every financial interaction.'],
                ['text' => 'Our belief is simple: finance should move people forward, not hold them back.'],
                ['text' => 'From that belief came our purpose — simplifying loans, amplifying trust.'],
            ],
            'founder_quote' => '"Simplifying Loans, Amplifying Trust."',
            'founder_role' => 'Founder, FynnEdge Advisory',
            'mission_label' => 'Our mission',
            'mission_title' => 'Make borrowing simple, transparent and fair.',
            'mission_body' => 'We match every applicant with lenders suited to their profile, show clear reasons behind every result, and never leave anyone guessing about what happens next.',
            'vision_label' => 'Our vision',
            'vision_title' => 'A future where comparing credit is as easy as comparing anything else.',
            'vision_body' => 'We want every borrower in India to be able to check where they stand, compare their real options, and choose with confidence — instead of applying blind and hoping for the best.',
            'life_eyebrow' => 'Life at FynnEdge',
            'life_heading' => 'A small team, building with real ownership.',
            'life_description' => "We're early-stage and small by design — everyone who joins shapes the product, not just their corner of it.",
            'values' => [
                ['title' => 'Real ownership', 'body' => 'Small team, so what you build actually ships — no layers between an idea and the product.'],
                ['title' => 'Customer first', 'body' => "Every decision starts from what makes the borrower's experience clearer and fairer."],
                ['title' => 'Built on trust', 'body' => 'We\'d rather say "not ready yet" than overstate what we can do — for customers and each other.'],
            ],
            'work_heading' => 'Work with us',
            'work_description' => "Interested in joining the team? We're a small crew and don't always have open roles listed, but we're always happy to hear from people who care about fixing lending.",
            'work_button_label' => 'See open roles',
        ];
    }

    /**
     * The saved content laid over the defaults, field by field, in the
     * visitor's language (always English in the admin panel).
     *
     * @return array{
     *     founder_heading: string,
     *     founder_heading_accent: string,
     *     founder_points: array<int, array{text: string}>,
     *     founder_quote: string,
     *     founder_role: string,
     *     mission_label: string,
     *     mission_title: string,
     *     mission_body: string,
     *     vision_label: string,
     *     vision_title: string,
     *     vision_body: string,
     *     life_eyebrow: string,
     *     life_heading: string,
     *     life_description: string,
     *     values: array<int, array{title: string, body: string}>,
     *     work_heading: string,
     *     work_description: string,
     *     work_button_label: string,
     * }
     */
    public static function resolve(): array
    {
        $saved = Setting::get(self::SETTING_KEY);
        $saved = is_array($saved) ? $saved : [];
        $content = [];

        foreach (self::defaults() as $key => $default) {
            $value = $saved[$key] ?? null;

            $content[$key] = match (true) {
                is_array($default) => is_array($value) ? self::cleanItems($value, self::REQUIRED_ITEM_KEYS[$key] ?? []) : $default,
                default => filled($value) ? trim((string) $value) : $default,
            };
        }

        if (! SiteLocale::isTranslating()) {
            return $content;
        }

        // A list the English copy hides stays hidden in Hindi too.
        $hindi = array_filter(
            self::hindi(),
            fn (mixed $value, string $key): bool => ! is_array($value) || $content[$key] !== [],
            ARRAY_FILTER_USE_BOTH,
        );

        return Phrasebook::localize($content, $hindi);
    }

    /**
     * The admin's own Hindi wording: only the fields filled in, cleaned the
     * same way as the English. What the admin form's Hindi tab edits.
     *
     * @return array<string, string|array<int, array<string, string>>>
     */
    public static function hindi(): array
    {
        $saved = Setting::get(self::HINDI_SETTING_KEY);
        $saved = is_array($saved) ? $saved : [];
        $hindi = [];

        foreach (self::defaults() as $key => $default) {
            $value = $saved[$key] ?? null;

            if (is_array($default) && is_array($value)) {
                $hindi[$key] = self::cleanItems($value, self::REQUIRED_ITEM_KEYS[$key] ?? []);
            } elseif (is_string($default) && is_scalar($value) && filled($value)) {
                $hindi[$key] = trim((string) $value);
            }
        }

        return array_filter($hindi, fn (mixed $value): bool => $value !== []);
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @param  array<int, string>  $requiredKeys
     * @return array<int, array<string, string>>
     */
    private static function cleanItems(array $items, array $requiredKeys): array
    {
        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => array_map(fn (mixed $value): string => trim((string) $value), $item))
            ->filter(fn (array $item): bool => collect($requiredKeys)->every(fn (string $key): bool => filled($item[$key] ?? null)))
            ->values()
            ->all();
    }
}
