<?php

namespace Database\Seeders;

use App\Enums\LandingPageGroup;
use App\Enums\PublishStatus;
use App\Models\Faq;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Support\Calculators\FlexiHybridLenderTerms;
use Illuminate\Database\Seeder;

/**
 * Runs after JourneySeeder (which creates the flexi-hybrid-term-loan
 * LoanProduct + its JourneyDefinition) and after LenderRosterSeeder (which
 * seeds the Lender identity rows this depends on).
 *
 * The four lender offers (Bajaj Finance, Tata Capital, Aditya Birla Finance,
 * Piramal Finance) and their researched terms and repayment structures live
 * in FlexiHybridLenderTerms, shared with the migration that brought existing
 * databases in line.
 */
class FlexiHybridTermLoanSeeder extends Seeder
{
    public function run(): void
    {
        $product = LoanProduct::query()->where('slug', 'flexi-hybrid-term-loan')->firstOrFail();

        $product->seoMeta()->updateOrCreate([], [
            'title' => 'Flexi Hybrid Term Loan — Compare Bajaj, Tata Capital, Aditya Birla & Piramal | FynnEdge',
            'description' => 'Compare Flexi Hybrid Term Loan options from Bajaj Finance, Tata Capital, Aditya Birla Finance and Piramal Finance. Understand your initial and subsequent repayment structure, then apply through FynnEdge.',
        ]);

        FlexiHybridLenderTerms::sync($product);

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
                'answer' => 'No. Each lender sets its own interest-only initial tenure + principal-and-interest subsequent tenure: Bajaj Finance offers 1 + 4, 2 + 6 or 3 + 6 years; Tata Capital gives a 1-year initial tenure on 5 or 6-year loans (1 + 4, 1 + 5) and 2 years on 7 or 8-year loans (2 + 5, 2 + 6); Aditya Birla Finance offers a 1 or 2-year initial tenure on 6, 7 or 8-year loans; and Piramal Finance offers 2 + 5 years. Compare them in the lender comparison table and calculator above before choosing.',
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
                    .'principal + interest EMIs for the subsequent tenure. Compare Bajaj Finance, Tata Capital, Aditya Birla Finance '
                    .'and Piramal Finance below, and check the exact eligibility criteria and documents required before you apply.</p>',
                'sort_order' => 1,
                'status' => PublishStatus::Published,
                'published_at' => now(),
            ],
        );
    }
}
