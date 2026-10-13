<?php

namespace App\Support\Rates;

use App\Models\LenderProductRate;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The rate audit as a CSV: one row per rate version in the given query (the
 * admin exports whatever its table currently shows), with the evidence and
 * the who/when of each workflow step, for compliance review or a spreadsheet.
 */
final class RateAuditCsv
{
    /**
     * @param  Builder<LenderProductRate>  $query
     */
    public static function download(Builder $query): StreamedResponse
    {
        $filename = 'interest-rate-audit-'.LenderProductRate::today()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_keys(self::row(null)));

            $query->clone()
                ->with(['lenderProduct.lender', 'lenderProduct.loanProduct', 'creator', 'submitter', 'publisher'])
                ->lazyById(200, 'lender_product_rates.id', 'id')
                ->each(fn (LenderProductRate $rate) => fputcsv($handle, array_map(self::safe(...), array_values(self::row($rate)))));

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed> Header => value (the header list when $rate is null).
     */
    public static function row(?LenderProductRate $rate): array
    {
        $offer = $rate?->lenderProduct;
        $dateTime = fn ($value) => $value?->timezone(LenderProductRate::TIMEZONE)->format('Y-m-d H:i');

        return [
            'Loan type' => $offer?->loanProduct?->name,
            'Lender' => $offer?->lender?->name,
            'Lender type' => $offer?->lender?->type?->getLabel(),
            'Offer status' => $offer?->status?->getLabel(),
            'Variant' => $rate?->variant,
            'Only for' => $rate?->borrower_segment,
            'Unit' => $rate?->rate_unit?->getLabel(),
            'Starting rate' => $rate?->rate_min,
            'Maximum rate' => $rate?->rate_max,
            'Rate type' => $rate?->rate_type?->getLabel(),
            'Repayment' => $rate?->repayment_method?->getLabel(),
            'Benchmark' => $rate?->benchmark,
            'Promotional rate' => $rate?->promo_rate,
            'Promotion name' => $rate?->promo_label,
            'Promotion starts' => $dateTime($rate?->promo_starts_at),
            'Promotion ends' => $dateTime($rate?->promo_ends_at),
            'Valid from' => $rate?->effective_from?->toDateString(),
            'Valid until' => $rate?->effective_until?->toDateString(),
            'Status' => $rate?->displayStatus(),
            'Evidence' => $rate?->verification_status?->getLabel(),
            'Needs re-verification' => $rate === null ? null : ($rate->needsReverification() ? 'Yes' : 'No'),
            'Last checked' => $rate?->last_verified_at?->toDateString(),
            'Source URL' => $rate?->source_url,
            'Source says' => $rate?->source_notes,
            'Processing fee' => $offer === null ? null : collect([$offer->processingFeeDisplay(), $offer->processing_fee_note])->filter()->implode(' — '),
            'Who gets this rate' => $rate?->eligibility_notes,
            'Disclaimer' => $rate?->disclaimer,
            'Featured' => $rate === null ? null : ($rate->is_featured ? 'Yes' : 'No'),
            'Version' => $rate?->version,
            'Created by' => $rate?->creator?->name,
            'Submitted by' => $rate?->submitter?->name,
            'Submitted at' => $dateTime($rate?->submitted_at),
            'Published by' => $rate?->publisher?->name,
            'Published at' => $dateTime($rate?->published_at),
            'Archived at' => $dateTime($rate?->archived_at),
        ];
    }

    /**
     * Neutralises spreadsheet formulas: a cell a spreadsheet would evaluate
     * (=, +, -, @ first) is prefixed with an apostrophe. Numbers pass through.
     */
    private static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && ! is_numeric($value) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
