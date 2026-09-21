<?php

namespace App\Support\Calculators;

use App\Enums\LoanCategory;
use App\Models\Setting;

/**
 * The heading copy on /calculators and every calculator page an admin can
 * reword (Admin → Website Settings → Calculators Page), kept in ONE Setting
 * so the pages are saved, cached and reset as a unit.
 *
 * The loan calculators (EMI, Eligibility, Prepayment) share one set of copy
 * per calculator type across every loan category: any `{loan}` in a field is
 * replaced with that page's loan name ("Home Loan", …) by forPage().
 *
 * Same additive contract as AboutPageContent: nothing saved means the pages
 * read exactly as built, and a blank field falls back to its default rather
 * than rendering empty.
 *
 * Not here by design: the "About this calculator" body (Content → Calculator
 * Pages for FD/SIP/Daily SIP/GST, the Loan Product's calculator explanation
 * for loan calculators) and the calculator links themselves (CalculatorCatalog).
 */
class CalculatorPagesContent
{
    public const SETTING_KEY = 'calculator_pages';

    public const LOAN_PLACEHOLDER = '{loan}';

    /**
     * @return array<string, array<string, string>>
     */
    public static function defaults(): array
    {
        return [
            'index' => [
                'meta_title' => 'Calculators',
                'meta_description' => 'Loan EMI, loan eligibility, prepayment and investment calculators — fixed deposit, SIP, GST and more.',
                'heading' => 'All calculators',
                'description' => 'Loan EMI, eligibility and prepayment calculators, plus everyday investment calculators — all in one place.',
            ],
            'emi' => [
                'meta_title' => '{loan} EMI Calculator',
                'meta_description' => 'Estimate your monthly EMI for a {loan} and see the full year-by-year principal and interest breakdown.',
                'heading' => '{loan} EMI Calculator',
                'description' => 'Adjust the amount, interest rate and tenure to see your monthly EMI — plus the full year-by-year principal and interest breakdown. You can switch loan types below.',
            ],
            'eligibility' => [
                'meta_title' => '{loan} Eligibility Calculator',
                'meta_description' => 'Get a quick, indicative {loan} eligibility estimate based on your income and existing obligations.',
                'heading' => '{loan} Eligibility Calculator',
                'description' => "Enter your details for a quick, indicative check against our lenders' published criteria.",
                'about_heading' => 'About the {loan} Eligibility Calculator',
            ],
            'prepayment' => [
                'meta_title' => '{loan} Prepayment Calculator',
                'meta_description' => 'See how a lumpsum prepayment reduces your {loan} tenure or EMI, and how much interest you save.',
                'heading' => '{loan} Prepayment Calculator',
                'description' => 'See how a lumpsum prepayment reduces your tenure or EMI, and how much interest you save.',
                'about_heading' => 'About the {loan} Prepayment Calculator',
            ],
            'fixed_deposit' => [
                'meta_title' => 'Fixed Deposit Calculator',
                'meta_description' => 'Estimate the maturity value and interest earned on a fixed deposit.',
                'heading' => 'Fixed Deposit Calculator',
                'description' => 'Adjust the deposit amount, interest rate and tenure to see the maturity value of your fixed deposit.',
            ],
            'sip' => [
                'meta_title' => 'SIP Calculator',
                'meta_description' => 'Estimate the maturity value of a monthly SIP investment.',
                'heading' => 'SIP Calculator',
                'description' => "Adjust the monthly investment, expected return and tenure to estimate your SIP's maturity value.",
            ],
            'daily_sip' => [
                'meta_title' => 'Daily SIP Calculator',
                'meta_description' => 'Estimate the maturity value of a daily SIP investment.',
                'heading' => 'Daily SIP Calculator',
                'description' => "Adjust the daily investment, expected return and tenure to estimate your daily SIP's maturity value.",
            ],
            'gst' => [
                'meta_title' => 'GST Calculator',
                'meta_description' => 'Add or remove GST from an amount and see the CGST/SGST split.',
                'heading' => 'GST Calculator',
                'description' => 'Add GST to a base amount, or work out the base amount and GST already included in a total.',
            ],
        ];
    }

    /**
     * The saved content laid over the defaults, field by field, with
     * `{loan}` still in place — what the admin form edits.
     *
     * @return array<string, array<string, string>>
     */
    public static function resolve(): array
    {
        $saved = Setting::get(self::SETTING_KEY);
        $saved = is_array($saved) ? $saved : [];

        return collect(self::defaults())
            ->map(fn (array $fields, string $page): array => collect($fields)
                ->map(function (string $default, string $field) use ($saved, $page): string {
                    $value = is_array($saved[$page] ?? null) ? ($saved[$page][$field] ?? null) : null;

                    return is_scalar($value) && filled($value) ? trim((string) $value) : $default;
                })
                ->all())
            ->all();
    }

    /**
     * One page's resolved copy, with `{loan}` replaced by the loan category's name.
     *
     * @return array<string, string>
     */
    public static function forPage(string $page, ?LoanCategory $category = null): array
    {
        $fields = self::resolve()[$page] ?? [];

        if (! $category) {
            return $fields;
        }

        return array_map(
            fn (string $text): string => str_ireplace(self::LOAN_PLACEHOLDER, $category->getLabel(), $text),
            $fields,
        );
    }
}
