<?php

namespace App\Support\Pages;

use App\Models\Setting;
use App\Support\Localization\Phrasebook;
use App\Support\Localization\SiteLocale;

/**
 * The reusable sections of the Debt Consolidation pages an admin can reword
 * (Website Settings → Debt Consolidation Pages), kept in ONE Setting. The
 * page title, intro, main body, SEO and FAQs live on each page's own
 * General Pages row; this holds the shared hero badge, calls to action,
 * trust points, steps, "who it is for" lists, form headings and which
 * sections each page shows.
 *
 * Same contract as AboutPageContent: a blank field falls back to its default,
 * a list never saved shows the defaults, a saved empty list hides the block,
 * and Hindi comes from the `_hi` setting, else lang/hi.json.
 */
class DebtConsolidationPageContent
{
    public const SETTING_KEY = 'debt_consolidation_pages';

    public const HINDI_SETTING_KEY = self::SETTING_KEY.'_hi';

    /**
     * Sections a page may show besides its title, intro, body and FAQs.
     *
     * @var array<string, string>
     */
    public const SECTIONS = [
        'enquiry' => 'Quick enquiry form',
        'trust' => 'Trust points',
        'steps' => 'How it works steps',
        'audiences' => 'Personal and business cards',
        'personal_points' => 'Debts covered for individuals',
        'business_points' => 'Debts covered for businesses',
        'tools' => 'Calculator and assessment cards',
        'related' => 'Related loan pages',
        'disclaimer' => 'Disclaimer',
    ];

    private const REQUIRED_ITEM_KEYS = [
        'trust_points' => ['text'],
        'steps' => ['title'],
        'personal_points' => ['text'],
        'business_points' => ['text'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'hero_badge' => 'Debt consolidation advisory',
            'primary_cta_label' => 'Start my free assessment',
            'secondary_cta_label' => 'Use the calculator',
            'trust_points' => [
                ['text' => 'Free, no-obligation assessment'],
                ['text' => 'We compare total cost, not just the EMI'],
                ['text' => 'Options from partner banks and NBFCs'],
                ['text' => 'Help closing old loans and collecting NOCs'],
            ],
            'steps_heading' => 'How debt consolidation works with FynnEdge',
            'steps' => [
                ['title' => 'Tell us about your loans', 'body' => 'List your loans and cards with their outstanding, EMI and rate. It takes about five minutes.'],
                ['title' => 'Get an honest assessment', 'body' => 'We compare what you would pay by keeping your loans with what one new loan would cost — including foreclosure and processing charges.'],
                ['title' => 'Choose a route that fits', 'body' => 'If consolidating helps, we shortlist banks and NBFCs whose published criteria fit your profile.'],
                ['title' => 'Apply with your consent', 'body' => 'We help you apply to the lender you choose. The lender alone decides whether to approve.'],
                ['title' => 'Close old loans and collect NOCs', 'body' => 'After disbursement we help coordinate closure of each old loan and track every NOC until it is received.'],
            ],
            'audiences_heading' => 'Who can consolidate?',
            'personal_heading' => 'For individuals',
            'personal_summary' => 'Salaried and self-employed people juggling several loans and credit cards.',
            'personal_points' => [
                ['text' => 'Personal loans and credit card outstanding'],
                ['text' => 'Credit card EMIs, consumer durable loans and BNPL'],
                ['text' => 'Vehicle, gold and education loans'],
                ['text' => 'Home loans and loans against property, where a refinance route exists'],
            ],
            'business_heading' => 'For businesses',
            'business_summary' => 'Proprietors, partnerships and companies with several business loans or facilities.',
            'business_points' => [
                ['text' => 'Unsecured and secured business loans, MSME and NBFC loans'],
                ['text' => 'Term, machinery and commercial vehicle loans'],
                ['text' => 'Cash credit, overdraft and working capital facilities'],
                ['text' => 'Invoice and merchant financing'],
            ],
            'tools_heading' => 'See your numbers first',
            'calculator_card_title' => 'Debt consolidation calculator',
            'calculator_card_body' => 'Add your loans and compare your current repayments with one consolidated loan — total cost, monthly outgo, foreclosure charges and break-even.',
            'assessment_card_title' => 'Free debt assessment',
            'assessment_card_body' => 'Save your loans, see your suitability score and let a FynnEdge advisor review your options with you.',
            'enquiry_heading' => 'Talk to a debt consolidation advisor',
            'enquiry_subheading' => 'Leave your number. We verify it with a one-time code and call you back — no obligation.',
            'related_heading' => 'Loans often used to consolidate debt',
            'page_sections' => [
                'hub' => ['trust', 'audiences', 'steps', 'tools', 'enquiry', 'related', 'disclaimer'],
                'personal' => ['trust', 'personal_points', 'steps', 'tools', 'enquiry', 'related', 'disclaimer'],
                'business' => ['trust', 'business_points', 'steps', 'tools', 'enquiry', 'related', 'disclaimer'],
                'how_it_works' => ['steps', 'tools', 'enquiry', 'disclaimer'],
                'calculator' => ['disclaimer'],
                'assessment' => ['trust', 'disclaimer'],
                'faqs' => ['tools', 'enquiry'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolve(): array
    {
        $saved = Setting::get(self::SETTING_KEY);
        $saved = is_array($saved) ? $saved : [];
        $content = [];

        foreach (self::defaults() as $key => $default) {
            $value = $saved[$key] ?? null;

            $content[$key] = match (true) {
                $key === 'page_sections' => self::cleanSections(is_array($value) ? $value : [], $default),
                is_array($default) => is_array($value) ? self::cleanItems($value, self::REQUIRED_ITEM_KEYS[$key] ?? []) : $default,
                default => filled($value) ? trim((string) $value) : $default,
            };
        }

        if (! SiteLocale::isTranslating()) {
            return $content;
        }

        $sections = $content['page_sections'];
        $hindi = array_filter(
            self::hindi(),
            fn (mixed $value, string $key): bool => ! is_array($value) || $content[$key] !== [],
            ARRAY_FILTER_USE_BOTH,
        );
        unset($content['page_sections']);

        return [...Phrasebook::localize($content, $hindi), 'page_sections' => $sections];
    }

    /**
     * Whether a page shows a section.
     */
    public static function shows(array $content, string $pageKey, string $section): bool
    {
        return in_array($section, $content['page_sections'][$pageKey] ?? [], true);
    }

    /**
     * @return array<string, mixed>
     */
    public static function hindi(): array
    {
        $saved = Setting::get(self::HINDI_SETTING_KEY);
        $saved = is_array($saved) ? $saved : [];
        $hindi = [];

        foreach (self::defaults() as $key => $default) {
            $value = $saved[$key] ?? null;

            if ($key === 'page_sections') {
                continue;
            }

            if (is_array($default) && is_array($value)) {
                $hindi[$key] = self::cleanItems($value, self::REQUIRED_ITEM_KEYS[$key] ?? []);
            } elseif (is_string($default) && is_scalar($value) && filled($value)) {
                $hindi[$key] = trim((string) $value);
            }
        }

        return array_filter($hindi, fn (mixed $value): bool => $value !== []);
    }

    /**
     * @param  array<array-key, mixed>  $saved
     * @param  array<string, list<string>>  $defaults
     * @return array<string, list<string>>
     */
    private static function cleanSections(array $saved, array $defaults): array
    {
        $sections = [];

        foreach ($defaults as $page => $pageDefaults) {
            $chosen = $saved[$page] ?? null;
            $sections[$page] = is_array($chosen)
                ? array_values(array_intersect(array_keys(self::SECTIONS), $chosen))
                : $pageDefaults;
        }

        return $sections;
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
