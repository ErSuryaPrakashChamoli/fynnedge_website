<?php

namespace App\Support\Calculators;

use App\Enums\LenderStatus;
use App\Enums\LenderType;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use Illuminate\Support\Str;

/**
 * The four lenders offered on the Flexi Hybrid Term Loan and their published
 * terms, researched from each lender's own product pages (September 2026):
 *
 * - Bajaj Finance (bajajfinserv.in/all-about-flexi-hybrid-loans): 10%–30.5%
 *   p.a., processing fee up to 4.13% incl. taxes, ₹40,000–₹55 lakh, tenure
 *   up to 108 months, interest-only EMIs for up to 36 months. Its own
 *   example is 12 + 48; 2 + 6 and 3 + 6 are its longer structures.
 * - Tata Capital (tatacapital.com/personal-loan/hybrid-term-loan.html):
 *   from 12.99% p.a., up to 5% + GST, ₹2–35 lakh. "For 60 and 72 months of
 *   tenure, the amount remains fixed for the first year … for 84 and 96
 *   months … the first two years."
 * - Aditya Birla Finance (Flexi Advantage): APR 12.50%–28%, up to 4% + GST,
 *   "only 6, 7 or 8 year tenures", principal holiday of 1 or 2 years.
 * - Piramal Finance: from 12.99% p.a., up to 5% + taxes, ₹50,000–₹25 lakh;
 *   84-month gross tenure with a 24-month initial and 60-month subsequent tenor.
 *
 * Bajaj is the default lender: the calculator opens on it and its structures
 * drive the generic estimate. Every figure stays editable in Admin → Lender
 * Offers; this class only seeds them (FlexiHybridTermLoanSeeder) and brought
 * existing databases in line once (the sync_flexi_hybrid_lender_offers migration).
 */
class FlexiHybridLenderTerms
{
    public const DEFAULT_LENDER_SLUG = 'bajaj-finance';

    /**
     * @return array<string, array<string, mixed>> lender name => lender_products attributes
     */
    public static function terms(): array
    {
        return [
            'Bajaj Finance' => [
                'min_amount' => 40_000, 'max_amount' => 5_500_000,
                'interest_rate_from' => 10.00, 'interest_rate_to' => 30.50,
                'processing_fee_percent_min' => null, 'processing_fee_percent_max' => 4.13, 'processing_fee_gst_extra' => false,
                'structures' => [[12, 48], [24, 72], [36, 72]],
            ],
            'Tata Capital' => [
                'min_amount' => 200_000, 'max_amount' => 3_500_000,
                'interest_rate_from' => 12.99, 'interest_rate_to' => null,
                'processing_fee_percent_min' => null, 'processing_fee_percent_max' => 5.00, 'processing_fee_gst_extra' => true,
                'structures' => [[12, 48], [12, 60], [24, 60], [24, 72]],
            ],
            'Aditya Birla Finance' => [
                'min_amount' => 50_000, 'max_amount' => 5_000_000,
                'interest_rate_from' => 12.50, 'interest_rate_to' => 28.00,
                'processing_fee_percent_min' => null, 'processing_fee_percent_max' => 4.00, 'processing_fee_gst_extra' => true,
                'structures' => [[12, 60], [24, 48], [12, 72], [24, 60], [12, 84], [24, 72]],
            ],
            'Piramal Finance' => [
                'min_amount' => 50_000, 'max_amount' => 2_500_000,
                'interest_rate_from' => 12.99, 'interest_rate_to' => null,
                'processing_fee_percent_min' => null, 'processing_fee_percent_max' => 5.00, 'processing_fee_gst_extra' => true,
                'structures' => [[24, 60]],
            ],
        ];
    }

    /**
     * Upserts the four offers on the product and deactivates any other
     * lender's offer on it, so exactly these four are compared.
     */
    public static function sync(LoanProduct $product): void
    {
        $lenderIds = [];

        foreach (self::terms() as $lenderName => $terms) {
            $lender = Lender::query()->firstOrCreate(
                ['slug' => Str::slug($lenderName)],
                ['name' => $lenderName, 'type' => LenderType::Nbfc, 'status' => LenderStatus::Active],
            );

            $structures = collect($terms['structures'])
                ->map(fn (array $structure): array => ['initial_months' => $structure[0], 'subsequent_months' => $structure[1]]);
            $totals = $structures->map(fn (array $structure): int => $structure['initial_months'] + $structure['subsequent_months']);

            unset($terms['structures']);

            LenderProduct::query()->updateOrCreate(
                ['lender_id' => $lender->id, 'loan_product_id' => $product->id],
                [
                    ...$terms,
                    'min_tenure_months' => $totals->min(),
                    'max_tenure_months' => $totals->max(),
                    'initial_tenure_months' => $structures->first()['initial_months'],
                    'hybrid_structures' => $structures->all(),
                    'status' => LenderStatus::Active,
                ],
            );

            $lenderIds[] = $lender->id;
        }

        LenderProduct::query()
            ->where('loan_product_id', $product->id)
            ->whereNotIn('lender_id', $lenderIds)
            ->update(['status' => LenderStatus::Inactive]);
    }
}
