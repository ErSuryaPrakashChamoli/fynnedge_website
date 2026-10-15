<?php

namespace App\Support\Rates;

use App\Enums\RateSourceType;
use App\Models\LenderProductRate;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Download verification sheet": the rates the admin table currently shows,
 * as a CSV whose columns are exactly the ones "Upload verified rates"
 * (LenderProductRateImporter) reads. The team checks each lender's page,
 * fills in source_name, source_quote and checked_on (and corrects the figures if the
 * lender's page differs), then uploads the same file back. source_type stays
 * "official" unless the figures were read on a comparison site
 * ("third_party"), which the website then labels with source_name.
 *
 * current_status, current_rate and audit_notes are there to help whoever
 * fills it in; the importer ignores them.
 */
final class RateVerificationSheet
{
    /**
     * @var list<string>
     */
    public const COLUMNS = [
        'reference', 'lender', 'loan_type', 'variant', 'borrower_segment', 'rate_unit', 'rate_min', 'rate_max',
        'rate_type', 'repayment_method', 'benchmark', 'source_url', 'source_type', 'source_name', 'source_quote', 'checked_on', 'valid_until',
        'eligibility_notes', 'disclaimer', 'current_status', 'current_rate', 'audit_notes',
    ];

    /**
     * @param  Builder<LenderProductRate>  $query
     */
    public static function download(Builder $query): StreamedResponse
    {
        $filename = 'interest-rates-to-verify-'.LenderProductRate::today()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::COLUMNS);

            $query->clone()
                ->with(['lenderProduct.lender', 'lenderProduct.loanProduct'])
                ->lazyById(200, 'lender_product_rates.id', 'id')
                ->each(fn (LenderProductRate $rate) => fputcsv($handle, array_map(self::safe(...), self::row($rate))));

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<mixed>
     */
    public static function row(LenderProductRate $rate): array
    {
        $offer = $rate->lenderProduct;

        return [
            $rate->public_id,
            $offer?->lender?->name,
            $offer?->loanProduct?->slug,
            $rate->variant,
            $rate->borrower_segment,
            $rate->rate_unit?->value,
            $rate->rate_min,
            $rate->rate_max,
            $rate->rate_type?->value,
            $rate->repayment_method?->value,
            $rate->benchmark,
            $rate->source_url,
            ($rate->source_type ?? RateSourceType::Official)->value,
            $rate->source_name,
            '',
            '',
            $rate->effective_until?->toDateString(),
            $rate->eligibility_notes,
            $rate->disclaimer,
            $rate->displayStatus().' / '.$rate->verification_status->getLabel(),
            RateFormat::range((float) $rate->rate_min, $rate->rate_max !== null ? (float) $rate->rate_max : null, $rate->rate_unit),
            mb_substr(str_replace(["\r", "\n"], ' ', (string) $rate->source_notes), 0, 1000),
        ];
    }

    /**
     * Neutralises spreadsheet formulas in text cells, as RateAuditCsv does.
     */
    private static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && ! is_numeric($value) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
