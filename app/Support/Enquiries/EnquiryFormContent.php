<?php

namespace App\Support\Enquiries;

use App\Models\MarketingSection;

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
 * Deliberately NOT editable here: the form's rate-and-ceiling headline
 * ("Get up to ₹50 Lakh starting at 10.49%"), which is generated from the loan
 * product's own min_interest_rate/max_amount fields. Those numbers are a
 * financial claim — they belong to the product record, not to marketing copy
 * that could drift away from what the calculator and lender table say.
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
            'heading' => self::resolve($section?->heading, 'Apply for a :product', $label),
            'description' => self::resolve($section?->description, $fallbackDescription, $label),
            // The small divider label above the form's headline.
            'eyebrow' => self::resolve($section?->subheading, 'Instant :product', $label),
            'ctaLabel' => self::resolve($section?->cta_label, 'Submit Enquiry', $label),
        ];
    }

    /**
     * Copy for the homepage Quick Enquiry box.
     *
     * @return array{heading: string, description: string, ctaLabel: string}
     */
    public static function forQuickEnquiry(): array
    {
        $section = MarketingSection::forPlacement(self::QUICK_PLACEMENT);

        return [
            'heading' => self::resolve($section?->heading, 'Get Started with a Quick Enquiry', ''),
            'description' => self::resolve(
                $section?->description,
                'Enter your mobile number and our team will get in touch with you.',
                '',
            ),
            'ctaLabel' => self::resolve($section?->cta_label, 'Submit Enquiry', ''),
        ];
    }

    /**
     * An admin value if there is one, otherwise the built-in default — with
     * `:product` substituted either way, so the placeholder is safe to use in a
     * default too. Blank admin fields fall through rather than rendering empty:
     * a cleared heading should restore the default, not delete the heading.
     */
    private static function resolve(?string $value, ?string $default, string $label): ?string
    {
        $copy = filled($value) ? $value : $default;

        return $copy === null ? null : str_replace(':product', $label, $copy);
    }
}
