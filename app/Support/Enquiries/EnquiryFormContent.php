<?php

namespace App\Support\Enquiries;

use App\Models\MarketingSection;
use App\Support\Localization\Phrasebook;

/**
 * The editable copy on the public enquiry forms, resolved from a published
 * MarketingSection (Admin → Marketing Sections) with the hardcoded wording as
 * the fallback. Nothing published for a placement means the site reads exactly
 * as it did before the row existed — the same additive contract every other
 * MarketingSection placement follows.
 *
 * `:product` in any admin-entered string is replaced with the product (or
 * landing page) the form is sitting on, so one row can word every loan page at
 * once without losing the name: "Apply for a :product" reads "Apply for a Home
 * Loan" on the home loan page.
 *
 * Everything comes back in the visitor's language: the row's own Hindi
 * columns (MarketingSection is translatable), else its English or the
 * built-in wording translated through lang/hi.json.
 *
 * Deliberately NOT editable here: the form's rate-and-ceiling headline
 * ("Get up to ₹50 Lakh starting at 9.99% p.a."). The ceiling is the loan
 * product's max_amount; the rate is the product's live, verified headline from
 * App\Support\Rates\RateBook, managed in Catalog → Interest Rates. Those
 * numbers are a financial claim — they must not drift away from what the
 * calculator and lender table say.
 */
class EnquiryFormContent
{
    public const LOAN_PLACEMENT = 'loan_enquiry_form';

    public const QUICK_PLACEMENT = 'home_quick_enquiry';

    /**
     * Copy for the loan-page enquiry section. `$label` is the product name, or
     * a landing page's own title when the form sits on one.
     *
     * @return array{heading: string, description: ?string, eyebrow: string, ctaLabel: string}
     */
    public static function forLoanPage(string $label, ?string $fallbackDescription = null): array
    {
        $section = MarketingSection::forPlacement(self::LOAN_PLACEMENT);

        return [
            'heading' => self::resolve($section?->heading, __('Apply for a :product', ['product' => $label]), $label),
            'description' => self::resolve($section?->description, $fallbackDescription, $label),
            // The small divider label above the form's headline.
            'eyebrow' => self::resolve($section?->subheading, __('Instant :product', ['product' => $label]), $label),
            'ctaLabel' => self::resolve($section?->cta_label, __('Submit Enquiry'), $label),
        ];
    }

    /**
     * Copy for the homepage Quick Enquiry box. `note` is the small line under
     * the phone field; the row's `subheading` column carries it, since the box
     * has no separate subheading of its own.
     *
     * @return array{heading: string, description: string, ctaLabel: string, note: string}
     */
    public static function forQuickEnquiry(): array
    {
        $section = MarketingSection::forPlacement(self::QUICK_PLACEMENT);

        return [
            'heading' => self::resolve($section?->heading, __('Get Started with a Quick Enquiry'), ''),
            'description' => self::resolve(
                $section?->description,
                __('Enter your mobile number and our team will get in touch with you.'),
                '',
            ),
            'ctaLabel' => self::resolve($section?->cta_label, __('Submit Enquiry'), ''),
            'note' => self::resolve(
                $section?->subheading,
                __('We only need your number to call you back. No forms and no documents at this stage.'),
                '',
            ),
        ];
    }

    /**
     * An admin value if there is one, otherwise the built-in default — with
     * `:product` substituted either way, so the placeholder is safe to use in a
     * default too. Blank admin fields fall through rather than rendering empty:
     * a cleared heading should restore the default, not delete the heading.
     * An admin value nobody translated is looked up in lang/hi.json for Hindi
     * visitors (so a row repeating the built-in wording still reads in Hindi).
     */
    private static function resolve(?string $value, ?string $default, string $label): ?string
    {
        $copy = filled($value) ? Phrasebook::translate($value) : $default;

        return $copy === null ? null : str_replace(':product', $label, $copy);
    }
}
