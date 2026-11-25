<?php

namespace App\Modules\Leads\Services;

use App\Modules\Leads\Models\LeadInboxEntry;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Download CSV" on the Lead Inbox: the rows the table currently shows
 * (search, filters, sort), from the sources the downloader may see. Cells are
 * formula-neutralised. No bureau report, PAN or OTP data is part of a row.
 */
final class LeadsCsv
{
    public const TIMEZONE = 'Asia/Kolkata';

    /**
     * @var list<string>
     */
    public const COLUMNS = ['Received (IST)', 'Source', 'Form / detail', 'Name', 'Mobile', 'Email', 'Loan product', 'Source page', 'UTM source', 'UTM medium', 'UTM campaign', 'Mobile verified', 'Status', 'Repeat submissions', 'FynnOn LMS'];

    /**
     * @param  Builder<LeadInboxEntry>  $query
     */
    public static function download(Builder $query): StreamedResponse
    {
        $filename = 'leads-'.now(self::TIMEZONE)->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::COLUMNS);

            $query->clone()
                ->with('loanProduct')
                ->lazy(200)
                ->each(fn (LeadInboxEntry $lead) => fputcsv($handle, array_map(self::safe(...), self::row($lead))));

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<mixed>
     */
    public static function row(LeadInboxEntry $lead): array
    {
        return [
            $lead->received_at?->timezone(self::TIMEZONE)->format('Y-m-d H:i'),
            $lead->source->getLabel(),
            $lead->detailLabel(),
            $lead->name,
            $lead->phone,
            $lead->email,
            $lead->loanProduct?->name,
            $lead->sourcePath(),
            $lead->utm_source,
            $lead->utm_medium,
            $lead->utm_campaign,
            $lead->verified_at ? 'Yes' : 'No',
            $lead->statusLabel(),
            $lead->repeat_count,
            $lead->syncStatus()->getLabel(),
        ];
    }

    private static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && ! is_numeric($value) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
