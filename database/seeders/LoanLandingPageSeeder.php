<?php

namespace Database\Seeders;

use App\Enums\LandingPageGroup;
use App\Enums\PublishStatus;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the "By Amount / By Type / By Need" landing pages that back the
 * header's Loans mega menu (App\Support\Loans\LoanMegaMenu). Runs after
 * JourneySeeder in DatabaseSeeder, so every category with a journey has its
 * LoanProduct row available to attach pages to.
 *
 * Body copy is assembled from each LoanProduct's own real seeded fields
 * (amount/tenure/rate range, eligibility points, documents) plus one
 * hand-written framing sentence per page — nothing here invents numbers or
 * criteria the product row doesn't already state.
 */
class LoanLandingPageSeeder extends Seeder
{
    private const DISCLAIMER = 'Eligibility and final terms are set by the lender at the time of application — the figures here are indicative starting points, not a guarantee of approval or of the exact rate you\'ll be offered.';

    public function run(): void
    {
        $personalLoan = LoanProduct::query()->where('slug', 'personal-loan')->firstOrFail();
        $homeLoan = LoanProduct::query()->where('slug', 'home-loan')->firstOrFail();
        $carLoan = LoanProduct::query()->where('slug', 'car-loan')->firstOrFail();
        $lap = LoanProduct::query()->where('slug', 'loan-against-property')->firstOrFail();
        $businessLoan = LoanProduct::query()->where('slug', 'business-loan')->firstOrFail();
        $creditCard = LoanProduct::query()->where('slug', 'credit-card')->firstOrFail();
        $goldLoan = LoanProduct::query()->where('slug', 'gold-loan')->firstOrFail();
        $twoWheelerLoan = LoanProduct::query()->where('slug', 'two-wheeler-loan')->firstOrFail();
        $termLoan = LoanProduct::query()->where('slug', 'term-loan')->firstOrFail();
        $tractorLoan = LoanProduct::query()->where('slug', 'tractor-loan')->firstOrFail();
        $mudraLoan = LoanProduct::query()->where('slug', 'mudra-loan')->firstOrFail();

        $this->seedByAmount($personalLoan, ['1 Lakh' => 100_000, '3 Lakh' => 300_000, '5 Lakh' => 500_000, '10 Lakh' => 1_000_000]);
        $this->seedByAmount($homeLoan, ['20 Lakh' => 2_000_000, '40 Lakh' => 4_000_000, '75 Lakh' => 7_500_000, '1 Crore' => 10_000_000]);
        $this->seedByAmount($carLoan, ['3 Lakh' => 300_000, '5 Lakh' => 500_000, '8 Lakh' => 800_000, '15 Lakh' => 1_500_000]);
        $this->seedByAmount($lap, ['20 Lakh' => 2_000_000, '50 Lakh' => 5_000_000, '1 Crore' => 10_000_000, '2 Crore' => 20_000_000]);
        $this->seedByAmount($businessLoan, ['5 Lakh' => 500_000, '10 Lakh' => 1_000_000, '25 Lakh' => 2_500_000, '50 Lakh' => 5_000_000]);
        $this->seedByAmount($goldLoan, ['50 Thousand' => 50_000, '1 Lakh' => 100_000, '3 Lakh' => 300_000, '5 Lakh' => 500_000]);
        $this->seedByAmount($twoWheelerLoan, ['50 Thousand' => 50_000, '1 Lakh' => 100_000, '2 Lakh' => 200_000, '3 Lakh' => 300_000]);
        $this->seedByAmount($termLoan, ['10 Lakh' => 1_000_000, '25 Lakh' => 2_500_000, '50 Lakh' => 5_000_000, '1 Crore' => 10_000_000]);
        $this->seedByAmount($tractorLoan, ['2 Lakh' => 200_000, '5 Lakh' => 500_000, '10 Lakh' => 1_000_000, '20 Lakh' => 2_000_000]);
        // Credit Card intentionally has no generic By Amount pages — it doesn't repay on a fixed loan-amount/EMI schedule.
        // Mudra Loan uses its own three official scheme tiers instead — seeded separately below.

        foreach ([$personalLoan, $homeLoan, $carLoan, $lap, $businessLoan, $creditCard, $goldLoan, $twoWheelerLoan, $termLoan, $tractorLoan, $mudraLoan] as $product) {
            $this->seedByType($product);
        }

        $this->seedMudraTiers($mudraLoan);

        $this->seedByNeed($personalLoan, [
            'Medical Expenses' => "A {$personalLoan->name} for medical expenses is usually disbursed fast, since it's unsecured and doesn't require pledging any asset — useful when a hospital bill can't wait for approval on a secured loan.",
            'Travel' => "Funding a trip with a {$personalLoan->name} means you repay it over a fixed tenure at a fixed EMI, rather than carrying it as revolving credit card debt at a higher rate.",
            'Wedding' => "Wedding costs often arrive in one lump sum rather than in stages, which is exactly what a {$personalLoan->name} is designed for — a single disbursal, repaid over a tenure you choose upfront.",
            'Home Renovation' => "A {$personalLoan->name} for home renovation doesn't require the property itself as collateral, unlike a loan against property — a reasonable trade-off for a smaller, shorter-term renovation budget.",
            'Debt Consolidation' => "Consolidating multiple high-cost debts (credit cards, other loans) into one {$personalLoan->name} can simplify repayment to a single EMI — worthwhile only if the new rate is genuinely lower than what you're consolidating.",
        ]);

        $this->seedByNeed($homeLoan, [
            'New Home Purchase' => "For a ready-to-move property, a {$homeLoan->name} is disbursed in full at registration, against the sale agreement and property documents.",
            'Under-Construction Property' => "For an under-construction property, a {$homeLoan->name} is typically disbursed in stages tied to construction milestones, rather than as one lump sum.",
            'Home Construction' => "If you're building on land you already own, a {$homeLoan->name} for construction is released in stages against a lender-approved construction plan and cost estimate.",
            'Balance Transfer' => "A {$homeLoan->name} balance transfer moves your existing outstanding loan to a new lender, usually pursued when a materially lower rate is available elsewhere — factor in transfer and processing charges before switching.",
        ]);

        $this->seedByNeed($carLoan, [
            'New Car Purchase' => "For a new car, a {$carLoan->name} is typically sanctioned against the dealer's on-road price quotation and covers a larger share of the vehicle's value than a used-car loan does.",
            'Used Car Purchase' => "A {$carLoan->name} for a used car is assessed against the vehicle's current market/appraised value, not its original price, and usually carries a shorter maximum tenure than a new-car loan.",
            'Balance Transfer / Refinance' => "Refinancing an existing {$carLoan->name} to a new lender can lower your rate or EMI — worth comparing only if the savings outweigh any foreclosure charges on your current loan.",
        ]);

        $this->seedByNeed($lap, [
            'Business Expansion' => "A {$lap->name} is a common way to fund business expansion at a lower rate than an unsecured business loan, since the property you own secures the loan.",
            'Debt Consolidation' => "Using a {$lap->name} to consolidate costlier unsecured debt can lower your overall interest outgo — but it puts the pledged property at risk if repayments aren't kept up, so weigh that trade-off carefully.",
            'Education' => "A {$lap->name} can fund higher education costs, typically at a lower rate than an unsecured education loan, using an owned property as security.",
            'Medical Expenses' => "For a large, planned medical expense, a {$lap->name} offers a bigger loan amount and lower rate than an unsecured personal loan, in exchange for pledging a property you own.",
        ]);

        $this->seedByNeed($businessLoan, [
            'Working Capital' => "A {$businessLoan->name} for working capital covers day-to-day operating costs — inventory, payroll, supplier payments — during gaps in your cash flow cycle.",
            'Equipment Purchase' => "A {$businessLoan->name} for equipment purchase funds machinery or tools upfront, repaid over a tenure typically aligned to the equipment's useful life.",
            'Business Expansion' => "Whether it's a new location, more staff or additional inventory, a {$businessLoan->name} for expansion is assessed heavily on your existing turnover and banking history.",
            'Inventory Funding' => "A {$businessLoan->name} for inventory funding helps bridge the cash gap between buying stock and collecting payment from customers.",
        ]);

        $this->seedByNeed($creditCard, [
            'Everyday Spending & Cashback' => "A cashback-oriented {$creditCard->name} returns a percentage of everyday spends (groceries, utilities, fuel) as statement credit — compare the cashback rate against any annual fee before applying.",
            'Travel & Rewards' => "A travel-focused {$creditCard->name} typically earns accelerated reward points on flight/hotel bookings, redeemable against travel — most valuable if you already spend regularly in those categories.",
            'Fuel Savings' => "A fuel-focused {$creditCard->name} waives the fuel surcharge and/or gives reward points on fuel spends — useful mainly if fuel is a meaningful share of your monthly spending.",
            'First Credit Card' => "A first {$creditCard->name} for someone with little or no credit history usually comes with a lower credit limit to start, building a repayment track record that can improve future eligibility.",
        ]);

        $this->seedByNeed($goldLoan, [
            'Medical Emergency' => "A {$goldLoan->name} is one of the fastest ways to raise funds for a medical emergency, since the gold itself is the collateral and there's little income documentation to assemble.",
            'Business Working Capital' => "Small business owners often use a {$goldLoan->name} to bridge short-term working capital gaps, given its typically fast disbursal and shorter tenure.",
            'Agricultural Needs' => "Farmers commonly use a {$goldLoan->name} for seasonal expenses like seeds, fertiliser or equipment, repaying once the harvest is sold.",
            'Debt Consolidation' => "A {$goldLoan->name} can consolidate costlier unsecured debt at a lower rate — but remember the gold is held by the lender as security until you repay in full.",
        ]);

        $this->seedByNeed($twoWheelerLoan, [
            'New Two Wheeler Purchase' => "For a new bike or scooter, a {$twoWheelerLoan->name} is sanctioned against the dealer's on-road price quotation.",
            'Used Two Wheeler Purchase' => "A {$twoWheelerLoan->name} for a used vehicle is assessed against its current market value, and usually carries a shorter maximum tenure than a new-vehicle loan.",
            'Balance Transfer / Refinance' => "Refinancing an existing {$twoWheelerLoan->name} to a new lender can lower your rate or EMI — compare against any foreclosure charges on your current loan first.",
        ]);

        $this->seedByNeed($termLoan, [
            'Business Expansion' => "A {$termLoan->name} funds a new location, more staff or additional capacity, repaid over a fixed tenure via scheduled EMIs.",
            'Equipment Purchase' => "A {$termLoan->name} for equipment funds machinery or tools upfront, with the repayment tenure typically aligned to the equipment's useful life.",
            'Working Capital' => "While working capital is usually financed with a revolving facility, some businesses use a {$termLoan->name} for a one-time, larger working-capital need instead.",
            'Debt Refinancing' => "Refinancing costlier existing business debt into a single {$termLoan->name} can simplify repayment and lower your overall interest outgo, if the new rate is genuinely better.",
        ]);

        $this->seedByNeed($tractorLoan, [
            'New Tractor Purchase' => "For a new tractor, a {$tractorLoan->name} is sanctioned against the dealer's quotation, with the tractor itself typically serving as security.",
            'Used Tractor Purchase' => "A {$tractorLoan->name} for a used tractor is assessed against its current value and condition, and usually carries a shorter tenure than for a new one.",
            'Farm Implements & Attachments' => "A {$tractorLoan->name} can also cover attached implements — trailers, cultivators, harvesters — purchased alongside or after the tractor.",
        ]);

        $this->seedByNeed($mudraLoan, [
            'Micro Enterprise Setup' => "A {$mudraLoan->name} at the Shishu stage is aimed at funding the initial setup of a new micro enterprise, before it has an operating track record.",
            'Working Capital for Small Business' => "An established micro or small business can use a {$mudraLoan->name} at the Kishor or Tarun stage for day-to-day working capital needs.",
            'Equipment for Small Business' => "A {$mudraLoan->name} can fund machinery or equipment for a micro enterprise, collateral-free, under the government-backed scheme.",
        ]);
    }

    /**
     * @param  array<string, int>  $tiers  Tier label => amount in rupees.
     */
    private function seedByAmount(LoanProduct $product, array $tiers): void
    {
        $sortOrder = 0;

        foreach ($tiers as $label => $amount) {
            $title = "{$label} {$product->name}";

            $paragraphs = [
                "Looking for a {$label} {$product->name}? {$product->summary}",
                $this->rangeSentence($product),
                $this->eligibilitySentence($product),
                self::DISCLAIMER,
            ];

            $this->upsertLandingPage(
                product: $product,
                group: LandingPageGroup::ByAmount,
                title: $title,
                amount: $amount,
                excerpt: "Eligibility, rates and how to apply for a {$label} {$product->name}.",
                paragraphs: $paragraphs,
                sortOrder: $sortOrder++,
            );
        }
    }

    private function seedByType(LoanProduct $product): void
    {
        $personas = [
            'Salaried' => "As a salaried applicant, lenders primarily assess your in-hand monthly salary, employer category and job stability when evaluating a {$product->name}.",
            'Self-Employed' => "As a self-employed applicant — whether a professional or business owner — lenders evaluate a {$product->name} using your income tax returns, business vintage and banking history rather than a salary slip.",
            'Women' => "Several lenders offer a preferential interest rate or a processing-fee waiver on a {$product->name} for women applicants — check with FynnEdge which of our partner lenders currently do.",
            'Senior Citizens' => "A {$product->name} for senior citizens is assessed on pension income or other regular income sources, with the tenure typically capped so the loan matures within the lender's maximum age limit.",
        ];

        $sortOrder = 0;

        foreach ($personas as $persona => $framing) {
            $title = "{$product->name} for {$persona}";

            $paragraphs = [
                $framing,
                $this->eligibilitySentence($product),
                $this->documentsSentence($product),
                self::DISCLAIMER,
            ];

            $this->upsertLandingPage(
                product: $product,
                group: LandingPageGroup::ByType,
                title: $title,
                amount: null,
                excerpt: "What a {$persona} applicant needs to know about a {$product->name}.",
                paragraphs: $paragraphs,
                sortOrder: $sortOrder++,
            );
        }
    }

    /**
     * @param  array<string, string>  $needs  Need label => hand-written framing sentence.
     */
    private function seedByNeed(LoanProduct $product, array $needs): void
    {
        $sortOrder = 0;

        foreach ($needs as $need => $framing) {
            $title = "{$product->name} for {$need}";

            $paragraphs = [
                $framing,
                $this->eligibilitySentence($product),
                self::DISCLAIMER,
            ];

            $this->upsertLandingPage(
                product: $product,
                group: LandingPageGroup::ByNeed,
                title: $title,
                amount: null,
                excerpt: "Using a {$product->name} for {$need}.",
                paragraphs: $paragraphs,
                sortOrder: $sortOrder++,
            );
        }
    }

    /**
     * Mudra's By Amount pages use the scheme's own three official tiers —
     * Shishu/Kishor/Tarun — instead of the generic "X Lakh Product" pattern
     * seedByAmount() produces, since those tiers are how the real government
     * scheme is actually structured.
     */
    private function seedMudraTiers(LoanProduct $product): void
    {
        $tiers = [
            'Shishu Loan (up to ₹50,000)' => ['amount' => 50_000, 'text' => "The Shishu tier of {$product->name} covers funding up to ₹50,000, aimed at a new or very early-stage micro enterprise."],
            'Kishor Loan (₹50,000 – ₹5 Lakh)' => ['amount' => 500_000, 'text' => "The Kishor tier of {$product->name} covers funding from ₹50,000 up to ₹5,00,000, for a business that's already partly established."],
            'Tarun Loan (₹5 Lakh – ₹10 Lakh)' => ['amount' => 1_000_000, 'text' => "The Tarun tier of {$product->name} covers funding from ₹5,00,000 up to ₹10,00,000, for a more established micro/small business looking to grow further."],
        ];

        $sortOrder = 0;

        foreach ($tiers as $title => $tier) {
            $paragraphs = [
                $tier['text'],
                $this->eligibilitySentence($product),
                self::DISCLAIMER,
            ];

            $this->upsertLandingPage(
                product: $product,
                group: LandingPageGroup::ByAmount,
                title: $title,
                amount: $tier['amount'],
                excerpt: "Eligibility and how to apply for the {$title}.",
                paragraphs: $paragraphs,
                sortOrder: $sortOrder++,
            );
        }
    }

    private function rangeSentence(LoanProduct $product): ?string
    {
        if (! $product->min_amount || ! $product->max_amount) {
            return null;
        }

        $tenure = $product->min_tenure_months && $product->max_tenure_months
            ? sprintf(' over a tenure of %d–%d months', $product->min_tenure_months, $product->max_tenure_months)
            : '';

        $rate = $product->min_interest_rate && $product->max_interest_rate
            ? sprintf(', at indicative rates of %s%%–%s%% p.a', self::trimRate($product->min_interest_rate), self::trimRate($product->max_interest_rate))
            : '';

        return sprintf(
            '%s is typically available from ₹%s to ₹%s%s%s.',
            $product->name,
            number_format((float) $product->min_amount),
            number_format((float) $product->max_amount),
            $tenure,
            $rate,
        );
    }

    private function eligibilitySentence(LoanProduct $product): ?string
    {
        if (empty($product->eligibility_points)) {
            return null;
        }

        return 'Lenders typically assess: '.implode('; ', $product->eligibility_points).'.';
    }

    private function documentsSentence(LoanProduct $product): ?string
    {
        if (empty($product->documents_required)) {
            return null;
        }

        return 'Documents commonly required: '.implode(', ', $product->documents_required).'.';
    }

    private static function trimRate(string|float $rate): string
    {
        return rtrim(rtrim(number_format((float) $rate, 2), '0'), '.');
    }

    /**
     * @param  array<int, string|null>  $paragraphs
     */
    private function upsertLandingPage(LoanProduct $product, LandingPageGroup $group, string $title, ?int $amount, string $excerpt, array $paragraphs, int $sortOrder): void
    {
        $body = collect($paragraphs)
            ->filter()
            ->map(fn (string $paragraph) => "<p>{$paragraph}</p>")
            ->implode('');

        LoanLandingPage::query()->updateOrCreate(
            ['slug' => Str::slug($title)],
            [
                'loan_product_id' => $product->id,
                'group' => $group,
                'title' => $title,
                'amount' => $amount,
                'excerpt' => $excerpt,
                'body' => $body,
                'sort_order' => $sortOrder,
                'status' => PublishStatus::Published,
                'published_at' => now(),
            ],
        );
    }
}
