<?php

namespace App\Support\Pages;

use App\Models\Setting;
use App\Modules\CreditScore\Enums\BureauName;
use App\Support\Localization\Phrasebook;
use App\Support\Localization\SiteLocale;

/**
 * Everything on the /credit-score/{bureau} pages an admin can reword (Admin →
 * Website Settings → Credit Score Page). Each bureau page has its OWN Setting
 * (`credit_score_page.cibil`, …), saved, cached and reset as a unit, so the
 * CIBIL page can say something different from the Experian page.
 *
 * A bureau never saved on its own falls back to the legacy shared Setting
 * (`credit_score_page`, from when one copy served all four pages), then to the
 * defaults. Any `{bureau}` in a field is replaced with that page's bureau name
 * by forBureau(), so the defaults still read correctly on every page.
 *
 * Same additive contract as AboutPageContent: nothing saved means the page
 * reads exactly as built, and a blank text field falls back to its default
 * rather than rendering empty. Lists differ on purpose — a list that was never
 * saved shows the defaults, but a saved empty list means the admin removed
 * every item, so that block is hidden.
 *
 * Hindi visitors get the admin's Hindi wording for that bureau page (a second
 * Setting, `credit_score_page.cibil_hi`, same shape), and every field left
 * blank there is the English translated through lang/hi.json — so a page
 * nobody has touched is fully Hindi. See Phrasebook::localize().
 *
 * Not here by design: the OTP/details/result steps of the check form itself,
 * and the list of bureaus (BureauName).
 */
class CreditScorePageContent
{
    /**
     * Legacy: the one Setting all four pages shared. Read-only fallback now.
     */
    public const SETTING_KEY = 'credit_score_page';

    public const BUREAU_PLACEHOLDER = '{bureau}';

    /**
     * A list item missing any of these is dropped rather than rendered half-empty.
     */
    private const REQUIRED_ITEM_KEYS = [
        'benefits' => ['text'],
        'stats' => ['value'],
    ];

    /**
     * Where this bureau page's own copy is stored.
     */
    public static function settingKey(BureauName $bureau): string
    {
        return self::SETTING_KEY.'.'.$bureau->value;
    }

    /**
     * Where this bureau page's Hindi wording is stored.
     */
    public static function hindiSettingKey(BureauName $bureau): string
    {
        return self::settingKey($bureau).'_hi';
    }

    /**
     * @return array{
     *     meta_title: string,
     *     meta_description: string,
     *     badge: string,
     *     heading: string,
     *     description: string,
     *     benefits_heading: string,
     *     benefits: array<int, array{text: string}>,
     *     stats: array<int, array{value: string, label: string}>,
     * }
     */
    public static function defaults(): array
    {
        return [
            'meta_title' => 'Free {bureau} Score',
            'meta_description' => 'Check your free {bureau} credit score — verify your mobile number, add a few details, and see where you stand.',
            'badge' => 'Free {bureau} score check',
            'heading' => 'Check your free {bureau} score & report',
            'description' => "Verify your mobile number, add a few details, and see an instant {bureau} score — it's free, and checking it here never affects your real credit history.",
            'benefits_heading' => 'Why check with FynnEdge?',
            'benefits' => [
                ['text' => 'Instant result — no paperwork, no waiting on hold'],
                ['text' => "Verified by mobile OTP, so it's really you checking"],
                ['text' => '100% free, and it never impacts your real credit score'],
            ],
            'stats' => [
                ['value' => 'Secure', 'label' => 'OTP-verified, no passwords stored'],
                ['value' => 'Free', 'label' => 'No cost, no hidden charges'],
                ['value' => '~2 min', 'label' => 'Mobile, OTP, a few details'],
            ],
        ];
    }

    /**
     * The bureau's saved content laid over the defaults, field by field, with
     * `{bureau}` still in place — what the admin form edits. In the visitor's
     * language (always English in the admin panel).
     *
     * @return array{
     *     meta_title: string,
     *     meta_description: string,
     *     badge: string,
     *     heading: string,
     *     description: string,
     *     benefits_heading: string,
     *     benefits: array<int, array{text: string}>,
     *     stats: array<int, array{value: string, label: string}>,
     * }
     */
    public static function resolve(BureauName $bureau): array
    {
        $saved = Setting::get(self::settingKey($bureau)) ?? Setting::get(self::SETTING_KEY);
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
            self::hindi($bureau),
            fn (mixed $value, string $key): bool => ! is_array($value) || $content[$key] !== [],
            ARRAY_FILTER_USE_BOTH,
        );

        return Phrasebook::localize($content, $hindi);
    }

    /**
     * The admin's own Hindi wording for this bureau page: only the fields
     * filled in, cleaned the same way as the English. What the admin form's
     * Hindi tab edits.
     *
     * @return array<string, string|array<int, array<string, string>>>
     */
    public static function hindi(BureauName $bureau): array
    {
        $saved = Setting::get(self::hindiSettingKey($bureau));
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
     * The resolved content with `{bureau}` replaced by the page's bureau name.
     *
     * @return array{
     *     meta_title: string,
     *     meta_description: string,
     *     badge: string,
     *     heading: string,
     *     description: string,
     *     benefits_heading: string,
     *     benefits: array<int, array{text: string}>,
     *     stats: array<int, array{value: string, label: string}>,
     * }
     */
    public static function forBureau(BureauName $bureau): array
    {
        $replace = fn (string $text): string => str_ireplace(self::BUREAU_PLACEHOLDER, $bureau->getLabel(), $text);

        return array_map(
            fn (mixed $value): mixed => is_array($value)
                ? array_map(fn (array $item): array => array_map($replace, $item), $value)
                : $replace($value),
            self::resolve($bureau),
        );
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
