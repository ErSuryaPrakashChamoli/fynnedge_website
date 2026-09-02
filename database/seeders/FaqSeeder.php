<?php

namespace Database\Seeders;

use App\Enums\PublishStatus;
use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * Site-wide general FAQs (faqable_id is null) — shown on the public /faqs page.
 * Product-specific FAQs are seeded alongside their own LoanProduct instead
 * (see DatabaseSeeder's Personal Loan block), per the general/product-attached
 * FAQ split documented in .ai/rules/loan-products.md.
 */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'What does FynnEdge do?',
                'answer' => 'FynnEdge is a loan advisory business — we help you compare suitable banks and NBFCs for your loan, based on your profile, rather than you applying to each one separately.',
            ],
            [
                'question' => 'Is there a fee to use FynnEdge?',
                'answer' => 'Checking your eligibility on FynnEdge is free. Any lender fees (processing fees, etc.) are disclosed by the lender before you proceed with an application.',
            ],
            [
                'question' => 'Does checking my eligibility affect my credit score?',
                'answer' => 'No — checking eligibility on FynnEdge uses your self-reported profile and does not perform a hard credit bureau check until you explicitly consent, later in the application process.',
            ],
            [
                'question' => 'Does an eligibility result guarantee loan approval?',
                'answer' => 'No. Eligibility results are indicative and based on the profile you share — the lender still verifies your details, documents and credit history before giving a final approval.',
            ],
            [
                'question' => 'How long does the loan application process take?',
                'answer' => 'Checking your eligibility takes a few minutes. Once you choose a lender and submit your application with documents, sanction timelines depend on that lender — typically a few hours to a few business days.',
            ],
            [
                'question' => 'What documents do I need to apply for a loan?',
                'answer' => 'Most lenders ask for a PAN card, address proof, income proof (salary slips or ITR) and recent bank statements. The exact list depends on the loan type and lender, and is shown to you during the application.',
            ],
            [
                'question' => 'Can I apply for a loan if I am self-employed?',
                'answer' => 'Yes. Many lenders on FynnEdge accept self-employed applicants, though the income proof required (ITR, GST returns, business bank statements) differs from that of salaried applicants.',
            ],
            [
                'question' => 'What credit score do I need to get a loan?',
                'answer' => 'There is no single cut-off — each lender sets its own criteria, and some weigh income, employment stability and existing obligations alongside credit score. A higher score generally improves your options and the interest rate offered.',
            ],
            [
                'question' => 'Can I apply if I already have an existing loan or credit card?',
                'answer' => 'Yes. Existing loans and credit cards do not disqualify you, but the EMIs you are already paying are factored into how much a lender is willing to offer, since it affects your repayment capacity.',
            ],
            [
                'question' => 'How is my monthly EMI calculated?',
                'answer' => 'Your EMI depends on the loan amount, interest rate and tenure you choose. Use our EMI calculators to see the exact monthly instalment and a full year-by-year breakdown before you apply.',
            ],
            [
                'question' => 'What is the difference between fixed and floating interest rates?',
                'answer' => 'A fixed rate stays the same for the loan tenure, so your EMI does not change. A floating rate moves with the lender\'s benchmark rate, so your EMI can go up or down over time. Each lender\'s offer specifies which applies.',
            ],
            [
                'question' => 'Can I prepay or foreclose my loan early?',
                'answer' => 'Most lenders allow part-prepayment or full foreclosure, though some charge a prepayment fee, especially on fixed-rate loans. Check the specific lender\'s terms, or use our prepayment calculators to see the potential savings.',
            ],
            [
                'question' => 'What happens if my loan application is rejected?',
                'answer' => 'A rejection from one lender does not mean you are ineligible everywhere — different lenders have different criteria. You can review the reasons shown and consider other matched lenders, or reapply later once your profile improves.',
            ],
            [
                'question' => 'How does FynnEdge choose which lenders to show me?',
                'answer' => 'We match the details you share (income, employment, existing obligations, loan requirement) against each participating lender\'s own published eligibility criteria, and show you the ones you are likely eligible for.',
            ],
            [
                'question' => 'Is my personal information safe with FynnEdge?',
                'answer' => 'Yes. Your information is used only to check eligibility and share your application with the lenders you choose to proceed with — see our Privacy Policy for full details on how your data is handled.',
            ],
            [
                'question' => 'Can I apply for a loan with a co-applicant?',
                'answer' => 'Many lenders support joint applications with a co-applicant (such as a spouse or parent), which can improve eligibility for a higher loan amount. This option, where available, is offered during the application step.',
            ],
            [
                'question' => 'How quickly is the loan amount disbursed after approval?',
                'answer' => 'Disbursal timelines vary by lender and loan type — unsecured loans like personal loans are often disbursed within a day or two of final approval, while secured loans (like home loans) can take longer due to property and document verification.',
            ],
            [
                'question' => 'Can I check the status of my application after submitting it?',
                'answer' => 'Yes — once you submit an application, you can revisit it anytime using the link provided to track its status and upload any additional documents a lender requests.',
            ],
            [
                'question' => 'What if I want to cancel my application?',
                'answer' => 'You can choose not to proceed with an application at any stage before final disbursal. Contact us or the lender directly if you have already submitted documents and wish to withdraw.',
            ],
            [
                'question' => 'Do I pay FynnEdge if I get a loan through a lender you matched me with?',
                'answer' => 'No, you do not pay FynnEdge anything. Any fees you pay (processing fees, etc.) go directly to the lender and are disclosed upfront by them, not charged by FynnEdge.',
            ],
            [
                'question' => 'Are the interest rates shown on FynnEdge final?',
                'answer' => 'The rates and ranges shown are indicative, based on what lenders publish for their products. Your final rate is confirmed by the lender after reviewing your complete profile and documents.',
            ],
            [
                'question' => 'How much loan amount can I get?',
                'answer' => 'The amount you are eligible for depends on your income, existing obligations, credit profile and the specific loan type. Use the eligibility calculators for a quick indicative estimate, or check your full eligibility for a precise range.',
            ],
        ];

        foreach ($faqs as $index => $faq) {
            Faq::query()->updateOrCreate(
                ['faqable_type' => null, 'faqable_id' => null, 'question' => $faq['question']],
                [
                    'answer' => $faq['answer'],
                    'sort_order' => $index + 1,
                    'status' => PublishStatus::Published,
                ],
            );
        }
    }
}
