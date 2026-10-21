<?php

namespace App\Modules\CreditScore\Services;

use App\Models\User;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Customers\Services\CustomerLocation;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Download CSV" on Credit Score Checks: the checks the admin table currently
 * shows (search, filters and sort applied), one row each, newest first by
 * default.
 *
 * Location is the customer's profile city, state and PIN when on file;
 * otherwise the first address in the bureau report behind the check, and only
 * for staff who may read that full report (the address is part of it). CRIF's
 * public check, demo and failed checks keep no report, so theirs stays blank.
 * Nothing here calls a bureau.
 */
final class CreditScoreChecksCsv
{
    public const TIMEZONE = 'Asia/Kolkata';

    /**
     * @var list<string>
     */
    public const COLUMNS = ['Name', 'Mobile number', 'Bureau', 'Score', 'Location', 'PAN', 'Date of application (IST)'];

    /**
     * @param  Builder<CreditScoreCheck>  $query
     */
    public static function download(Builder $query, ?User $viewer): StreamedResponse
    {
        $filename = 'credit-score-checks-'.now(self::TIMEZONE)->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query, $viewer): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::COLUMNS);

            $query->clone()
                ->with('customer')
                ->orderByDesc('credit_score_checks.id')
                ->lazy(200)
                ->each(fn (CreditScoreCheck $check) => fputcsv($handle, array_map(self::safe(...), self::row($check, $viewer))));

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<mixed>
     */
    public static function row(CreditScoreCheck $check, ?User $viewer): array
    {
        $source = $check->sourceBureau();

        return [
            $check->full_name,
            $check->mobile_number,
            $source !== null && $source !== $check->bureau
                ? $check->bureau?->getLabel().' (answered by '.$source->getLabel().')'
                : $check->bureau?->getLabel(),
            $check->score,
            self::location($check, $viewer),
            $check->pan_number,
            $check->created_at?->timezone(self::TIMEZONE)->format('Y-m-d H:i'),
        ];
    }

    /**
     * The customer's own city, state and PIN, else the first address the
     * bureau report lists, else null.
     */
    public static function location(CreditScoreCheck $check, ?User $viewer): ?string
    {
        return CustomerLocation::fromProfile($check->customer)
            ?? ($check->isSuccessful() ? CustomerLocation::fromReport($check->bureauRequest(), $viewer) : null);
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
