<?php

namespace App\Support\Enquiries;

use App\Models\Setting;

/**
 * Everything on the /quick-enquiry page an admin can reword (Admin → Website
 * Settings → Quick Enquiry Page), kept in ONE Setting so the page is saved,
 * cached and reset as a unit.
 *
 * Same additive contract as EnquiryFormContent: nothing saved means the page
 * reads exactly as built, and a blank text field falls back to its default
 * rather than rendering empty. Lists differ on purpose — a list that was never
 * saved shows the defaults, but a saved empty list means the admin removed
 * every item, so that block is hidden.
 *
 * Deliberately NOT editable here: the form's "Get up to ₹X starting at Y%"
 * headline and the amount range, which come from each loan product's own fields.
 */
class QuickEnquiryPageContent
{
    public const SETTING_KEY = 'quick_enquiry_page';

    /**
     * Admin-entered links must be http(s) or site-relative, the same guard
     * MarketingSection.cta_url uses — it blocks javascript: and friends.
     */
    public const SAFE_URL_PATTERN = '#^(https?://|/)#i';

    /**
     * A list item missing any of these is dropped rather than rendered half-empty.
     */
    private const REQUIRED_ITEM_KEYS = [
        'assurances' => ['text'],
        'steps' => ['title'],
        'explore_links' => ['label', 'url'],
    ];

    /**
     * @return array{
     *     meta_title: string,
     *     meta_description: string,
     *     home_button_label: string,
     *     badge: string,
     *     heading: string,
     *     heading_accent: string,
     *     description: string,
     *     assurances: array<int, array{text: string}>,
     *     steps: array<int, array{title: string, body: string}>,
     *     show_lenders: bool,
     *     lenders_label: string,
     *     form_eyebrow: string,
     *     form_headline: string,
     *     form_cta_label: string,
     *     explore_heading: string,
     *     explore_description: string,
     *     explore_links: array<int, array{label: string, body: string, url: string, link_text: string}>,
     * }
     */
    public static function defaults(): array
    {
        return [
            'meta_title' => 'Quick Loan Enquiry',
            'meta_description' => 'Choose a loan type, share a few details and a FynnEdge advisor will call you back with suitable options from our partner banks and NBFCs.',
            'home_button_label' => 'Quick Enquiry',
            'badge' => 'Quick Enquiry',
            'heading' => 'Tell us what you need.',
            'heading_accent' => "We'll find the right lender.",
            'description' => 'Pick a loan type and share a few details. An advisor will call you to talk through suitable options from our partner banks and NBFCs.',
            'assurances' => [
                ['text' => 'Takes under a minute'],
                ['text' => 'No documents needed at this stage'],
                ['text' => 'Your details are used only for this enquiry'],
            ],
            'steps' => [
                ['title' => 'Pick your loan', 'body' => 'Choose a loan type and tell us how much you need.'],
                ['title' => 'We shortlist lenders', 'body' => 'We match your needs against our partner banks and NBFCs.'],
                ['title' => 'Get a call back', 'body' => 'An advisor walks you through the options that suit you.'],
            ],
            'show_lenders' => true,
            'lenders_label' => 'Our partner banks & NBFCs',
            'form_eyebrow' => 'Quick Loan Enquiry',
            'form_headline' => 'Get the funds you need',
            'form_cta_label' => 'Submit Enquiry',
            'explore_heading' => 'Prefer to look around first?',
            'explore_description' => 'Everything below is free to use, with no sign-up.',
            'explore_links' => [
                ['label' => 'Check Your Eligibility', 'body' => 'Answer a short profile and see which lenders fit.', 'url' => route('eligibility.index', absolute: false), 'link_text' => 'Go'],
                ['label' => 'Explore Loan Products', 'body' => 'Compare rates, amounts and tenures side by side.', 'url' => route('loans.index', absolute: false), 'link_text' => 'Go'],
                ['label' => 'Loan Calculators', 'body' => 'Work out your EMI before you speak to anyone.', 'url' => route('calculators.index', absolute: false), 'link_text' => 'Go'],
            ],
        ];
    }

    /**
     * The saved content laid over the defaults, field by field.
     *
     * @return array{
     *     meta_title: string,
     *     meta_description: string,
     *     home_button_label: string,
     *     badge: string,
     *     heading: string,
     *     heading_accent: string,
     *     description: string,
     *     assurances: array<int, array{text: string}>,
     *     steps: array<int, array{title: string, body: string}>,
     *     show_lenders: bool,
     *     lenders_label: string,
     *     form_eyebrow: string,
     *     form_headline: string,
     *     form_cta_label: string,
     *     explore_heading: string,
     *     explore_description: string,
     *     explore_links: array<int, array{label: string, body: string, url: string, link_text: string}>,
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
                is_bool($default) => is_bool($value) ? $value : $default,
                default => filled($value) ? trim((string) $value) : $default,
            };
        }

        return $content;
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
            ->reject(fn (array $item): bool => isset($item['url']) && ! preg_match(self::SAFE_URL_PATTERN, $item['url']))
            ->values()
            ->all();
    }
}
