<?php

namespace Database\Seeders;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\LoanProduct;
use Illuminate\Database\Seeder;

/**
 * Seeds published LoanProduct rows for the newer loan types. Runs before
 * JourneySeeder in DatabaseSeeder — JourneySeeder fetches these rows by slug
 * to attach each one's own JourneyDefinition, and LoanLandingPageSeeder
 * fetches them afterward to attach their By Amount/Type/Need landing pages.
 *
 * Calculator limits mirror LoanProductFactory::withCalculatorLimits() —
 * illustrative Indian market ranges, editable by an admin at any time through
 * the Loan Products resource.
 */
class CalculatorLoanProductSeeder extends Seeder
{
    public function run(): void
    {
        LoanProduct::query()->updateOrCreate(
            ['slug' => 'gold-loan'],
            [
                'name' => 'Gold Loan',
                'category' => LoanCategory::GoldLoan,
                'summary' => 'Quick funding against your gold jewellery, matched to lenders based on the gold\'s value.',
                'body' => '<p>A gold loan lets you unlock funds against gold jewellery you already own, typically with fast '
                    .'disbursal and minimal documentation compared to unsecured loans.</p>',
                'features' => ['Fast disbursal', 'Minimal documentation', 'Gold held securely with the lender until repayment'],
                'eligibility_points' => ['Ownership of gold jewellery meeting the lender\'s purity requirements', 'Valid identity and address proof', 'Age 18 and above'],
                'documents_required' => ['PAN Card', 'Address Proof', 'Gold Jewellery for Appraisal'],
                'process_steps' => ['Check eligibility', 'Compare lenders', 'Gold appraisal', 'Apply online', 'Get sanctioned'],
                'status' => PublishStatus::Published,
                'published_at' => now(),
                'min_amount' => 10_000,
                'max_amount' => 5_000_000,
                'default_amount' => 200_000,
                'min_tenure_months' => 3,
                'max_tenure_months' => 36,
                'default_tenure_months' => 12,
                'min_interest_rate' => 8.50,
                'max_interest_rate' => 15.00,
                'default_interest_rate' => 8.50,
                'interest_rate_note' => null,
            ],
        );

        LoanProduct::query()->updateOrCreate(
            ['slug' => 'two-wheeler-loan'],
            [
                'name' => 'Two Wheeler Loan',
                'category' => LoanCategory::TwoWheelerLoan,
                'summary' => 'Finance a new or used two wheeler, matched to lenders based on the vehicle and your income profile.',
                'body' => '<p>FynnEdge helps you compare two wheeler loan offers from multiple lenders, whether you\'re buying '
                    .'a new bike or scooter or a pre-owned one.</p>',
                'features' => ['Funding for new and used two wheelers', 'Quick approval turnaround', 'Flexible tenure'],
                'eligibility_points' => ['Salaried or self-employed with a regular income', 'Minimum age as per lender policy', 'Reasonable existing obligations relative to income'],
                'documents_required' => ['PAN Card', 'Address Proof', 'Income Proof', 'Vehicle Quotation/Invoice'],
                'process_steps' => ['Check eligibility', 'Compare lenders', 'Apply online', 'Upload documents', 'Get sanctioned'],
                'status' => PublishStatus::Published,
                'published_at' => now(),
                'min_amount' => 20_000,
                'max_amount' => 500_000,
                'default_amount' => 100_000,
                'min_tenure_months' => 6,
                'max_tenure_months' => 48,
                'default_tenure_months' => 24,
                'min_interest_rate' => 9.50,
                'max_interest_rate' => 18.00,
                'default_interest_rate' => 9.50,
                'interest_rate_note' => null,
            ],
        );

        LoanProduct::query()->updateOrCreate(
            ['slug' => 'term-loan'],
            [
                'name' => 'Term Loan',
                'category' => LoanCategory::TermLoan,
                'summary' => 'Fixed-tenure business funding for expansion, equipment or working capital, repaid via scheduled EMIs.',
                'body' => '<p>A term loan provides a lump sum upfront, repaid over a fixed tenure through scheduled EMIs — '
                    .'suited to equipment purchase, expansion or one-time business needs.</p>',
                'features' => ['Lump sum disbursal', 'Fixed repayment schedule', 'Suited to larger one-time business needs'],
                'eligibility_points' => ['Minimum business vintage (varies by lender)', 'Healthy annual turnover', 'Reasonable existing business obligations'],
                'documents_required' => ['PAN Card', 'Business Registration Proof', 'GST Returns', 'Bank Statements (last 12 months)', 'ITR (last 2 years)'],
                'process_steps' => ['Check eligibility', 'Compare lenders', 'Apply online', 'Business & banking verification', 'Get sanctioned'],
                'status' => PublishStatus::Published,
                'published_at' => now(),
                'min_amount' => 100_000,
                'max_amount' => 50_000_000,
                'default_amount' => 1_000_000,
                'min_tenure_months' => 12,
                'max_tenure_months' => 120,
                'default_tenure_months' => 60,
                'min_interest_rate' => 10.00,
                'max_interest_rate' => 20.00,
                'default_interest_rate' => 10.00,
                'interest_rate_note' => null,
            ],
        );

        LoanProduct::query()->updateOrCreate(
            ['slug' => 'tractor-loan'],
            [
                'name' => 'Tractor Loan',
                'category' => LoanCategory::TractorLoan,
                'summary' => 'Finance a new or used tractor and farm equipment, matched to lenders based on land holding and income.',
                'body' => '<p>FynnEdge helps farmers and agri-businesses compare tractor loan offers from multiple lenders, '
                    .'for new or pre-owned tractors and attached implements.</p>',
                'features' => ['Funding for tractors and implements', 'Repayment aligned to harvest cycles on select lenders', 'Flexible tenure'],
                'eligibility_points' => ['Land ownership or cultivation rights', 'Regular agricultural or allied income', 'Minimum age as per lender policy'],
                'documents_required' => ['PAN Card', 'Address Proof', 'Land Records', 'Income Proof', 'Vehicle Quotation/Invoice'],
                'process_steps' => ['Check eligibility', 'Compare lenders', 'Apply online', 'Upload documents', 'Get sanctioned'],
                'status' => PublishStatus::Published,
                'published_at' => now(),
                'min_amount' => 100_000,
                'max_amount' => 2_500_000,
                'default_amount' => 600_000,
                'min_tenure_months' => 12,
                'max_tenure_months' => 84,
                'default_tenure_months' => 60,
                'min_interest_rate' => 9.00,
                'max_interest_rate' => 16.00,
                'default_interest_rate' => 9.00,
                'interest_rate_note' => null,
            ],
        );

        LoanProduct::query()->updateOrCreate(
            ['slug' => 'mudra-loan'],
            [
                'name' => 'Mudra Loan',
                'category' => LoanCategory::MudraLoan,
                'summary' => 'Government-backed collateral-free micro-business funding under the Pradhan Mantri Mudra Yojana.',
                'body' => '<p>Mudra loans are collateral-free loans for small and micro enterprises under the Pradhan Mantri '
                    .'Mudra Yojana, offered across Shishu, Kishor and Tarun categories based on the funding stage of the business.</p>',
                'features' => ['Collateral-free', 'Government-backed scheme', 'Tiered by business stage (Shishu/Kishor/Tarun)'],
                'eligibility_points' => ['Non-farm income-generating micro/small enterprise', 'Business plan or existing business proof', 'No default with any bank/NBFC'],
                'documents_required' => ['PAN Card', 'Address Proof', 'Business Proof/Plan', 'Bank Statements (last 6 months)'],
                'process_steps' => ['Check eligibility', 'Compare lenders', 'Apply online', 'Business verification', 'Get sanctioned'],
                'status' => PublishStatus::Published,
                'published_at' => now(),
                'min_amount' => 50_000,
                'max_amount' => 1_000_000,
                'default_amount' => 300_000,
                'min_tenure_months' => 12,
                'max_tenure_months' => 60,
                'default_tenure_months' => 36,
                'min_interest_rate' => 8.00,
                'max_interest_rate' => 12.00,
                'default_interest_rate' => 8.00,
                'interest_rate_note' => 'Government scheme (Shishu/Kishor/Tarun) — amount capped by category.',
            ],
        );
    }
}
