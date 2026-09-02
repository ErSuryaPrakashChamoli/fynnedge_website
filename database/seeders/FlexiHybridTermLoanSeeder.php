<?php

namespace Database\Seeders;

use App\Enums\LandingPageGroup;
use App\Enums\LenderStatus;
use App\Enums\PublishStatus;
use App\Models\Faq;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Runs after JourneySeeder (which creates the flexi-hybrid-term-loan
 * LoanProduct + its JourneyDefinition) and after LenderRosterSeeder (which
 * seeds the Lender identity rows this depends on).
 *
 * ILLUSTRATIVE EXAMPLE RATES — not sourced from a real rate card. No real,
 * confirmed Bajaj Finance / Tata Capital / Piramal Finance / Kotak Mahindra
 * Bank commercial terms for this specific product were available at rollout,
 * so the figures below (rate, fees, tenure range, initial tenure) are
 * plausible placeholders chosen only so the public comparison table and
 * calculator have real numbers to compute against end-to-end, and so every
 * lender's figures genuinely differ (proving the "compare all lenders" and
 * per-lender-configuration features actually work, not just render
 * identical numbers). Every one of these fields is editable via
 * Admin → Lender Offers — replace them with confirmed rates before this
 * page goes live, per [[flexi-hybrid-lenders-no-real-terms]] in
 * .ai/rules/seeders-seeders.md.
 */
class FlexiHybridTermLoanSeeder extends Seeder
{
    public function run(): void
    {
        $product = LoanProduct::query()->where('slug', 'flexi-hybrid-term-loan')->firstOrFail();

        $product->seoMeta()->updateOrCreate([], [
            'title' => 'Flexi Hybrid Term Loan — Compare Bajaj, Tata Capital, Piramal & Kotak | FynnEdge',
            'description' => 'Compare Flexi Hybrid Term Loan options from Bajaj Finance, Tata Capital, Piramal Finance and Kotak. Understand your initial and subsequent repayment structure, then apply through FynnEdge.',
        ]);

        // Interest-rate ranges deliberately overlap (a realistic market spread)
        // while initial_tenure_months genuinely differs per lender, so the
        // "compare all lenders" table and calculator visibly produce different
        // Initial/Subsequent EMI figures per lender rather than one repeated set.
        $illustrativeTerms = [
            'Bajaj Finance' => [
                'min_amount' => 200_000, 'max_amount' => 15_000_000,
                'min_tenure_months' => 24, 'max_tenure_months' => 72, 'initial_tenure_months' => 12,
                'interest_rate_from' => 10.50, 'interest_rate_to' => 14.00,
                'processing_fee_percent_min' => 1.00, 'processing_fee_percent_max' => 2.00, 'processing_fee_gst_extra' => true,
            ],
            'Tata Capital' => [
                'min_amount' => 150_000, 'max_amount' => 12_000_000,
                'min_tenure_months' => 24, 'max_tenure_months' => 72, 'initial_tenure_months' => 9,
                'interest_rate_from' => 10.75, 'interest_rate_to' => 14.50,
                'processing_fee_percent_min' => 1.25, 'processing_fee_percent_max' => 2.25, 'processing_fee_gst_extra' => true,
            ],
            'Piramal Finance' => [
                'min_amount' => 200_000, 'max_amount' => 10_000_000,
                'min_tenure_months' => 24, 'max_tenure_months' => 60, 'initial_tenure_months' => 6,
                'interest_rate_from' => 11.25, 'interest_rate_to' => 15.00,
                'processing_fee_percent_min' => 1.50, 'processing_fee_percent_max' => 2.50, 'processing_fee_gst_extra' => true,
            ],
            'Kotak Mahindra Bank' => [
                'min_amount' => 300_000, 'max_amount' => 20_000_000,
                'min_tenure_months' => 24, 'max_tenure_months' => 72, 'initial_tenure_months' => 12,
                'interest_rate_from' => 10.00, 'interest_rate_to' => 13.50,
                'processing_fee_percent_min' => 0.75, 'processing_fee_percent_max' => 1.75, 'processing_fee_gst_extra' => true,
            ],
        ];

        foreach ($illustrativeTerms as $lenderName => $terms) {
            $lender = Lender::query()->where('slug', Str::slug($lenderName))->first();

            if (! $lender) {
                continue;
            }

            LenderProduct::query()->updateOrCreate(
                ['lender_id' => $lender->id, 'loan_product_id' => $product->id],
                [...$terms, 'status' => LenderStatus::Active],
            );
        }

        Faq::query()->updateOrCreate(
            ['faqable_type' => LoanProduct::class, 'faqable_id' => $product->id, 'sort_order' => 1],
            [
                'question' => 'What does "interest-only" mean during the initial tenure?',
                'answer' => 'During the initial tenure, your monthly payment covers only the interest on the loan — the outstanding principal doesn\'t reduce during this period. Once the initial tenure ends, the loan converts to a standard EMI covering both principal and interest for the subsequent tenure.',
                'status' => PublishStatus::Published,
            ],
        );

        Faq::query()->updateOrCreate(
            ['faqable_type' => LoanProduct::class, 'faqable_id' => $product->id, 'sort_order' => 2],
            [
                'question' => 'Is the initial tenure the same for every lender?',
                'answer' => 'No. Each lender configures its own initial tenure, subsequent tenure and interest rate for this product — compare them in the lender comparison table and calculator above before choosing.',
                'status' => PublishStatus::Published,
            ],
        );

        Faq::query()->updateOrCreate(
            ['faqable_type' => LoanProduct::class, 'faqable_id' => $product->id, 'sort_order' => 3],
            [
                'question' => 'Can I prepay or foreclose a Flexi Hybrid Term Loan?',
                'answer' => 'Prepayment and foreclosure terms are set by each lender individually. Check the specific lender\'s terms during your application, or ask a FynnEdge expert before you apply.',
                'status' => PublishStatus::Published,
            ],
        );

        LoanLandingPage::query()->updateOrCreate(
            ['slug' => 'flexi-hybrid-term-loan-for-business-expansion'],
            [
                'loan_product_id' => $product->id,
                'group' => LandingPageGroup::ByNeed,
                'title' => 'Flexi Hybrid Term Loan for Business Expansion',
                'excerpt' => 'Lower initial outflow while you scale, then move to standard EMIs once your expansion is generating returns.',
                'cta_label' => null,
                'body' => '<p>Expanding a business often means a gap between spending on growth and seeing the returns from it. '
                    .'A Flexi Hybrid Term Loan\'s interest-only initial tenure can ease that gap, before converting to standard '
                    .'principal + interest EMIs for the subsequent tenure. Compare Bajaj Finance, Tata Capital, Piramal Finance '
                    .'and Kotak below, and check the exact eligibility criteria and documents required before you apply.</p>',
                'sort_order' => 1,
                'status' => PublishStatus::Published,
                'published_at' => now(),
            ],
        );
    }
}
