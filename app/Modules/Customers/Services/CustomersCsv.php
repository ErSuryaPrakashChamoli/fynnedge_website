<?php

namespace App\Modules\Customers\Services;

use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Models\User;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Experian\Models\ExperianCreditRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Download CSV" on Customers: the customers the admin table currently shows
 * (search, filters and sort applied), one row each.
 *
 * The PAN is the masked copy the admin shows; the full PAN stays encrypted.
 * Each bureau column holds the latest request's status and score, and appears
 * only for staff who may list that bureau's requests, as in the table.
 * Location follows CustomerLocation. Nothing here calls a bureau.
 */
final class CustomersCsv
{
    public const TIMEZONE = 'Asia/Kolkata';

    /**
     * Bureau column label => [latest-request relation, request model], in the table's order.
     *
     * @var array<string, array{0: string, 1: class-string<Model>}>
     */
    private const BUREAUS = [
        'CRIF' => ['latestCrifReport', CrifReport::class],
        'Equifax' => ['latestEquifaxRequest', EquifaxCreditRequest::class],
        'Experian' => ['latestExperianRequest', ExperianCreditRequest::class],
        'CIBIL' => ['latestCibilRequest', CibilCreditRequest::class],
    ];

    /**
     * @param  Builder<Customer>  $query
     */
    public static function download(Builder $query, ?User $viewer): StreamedResponse
    {
        $filename = 'customers-'.now(self::TIMEZONE)->format('Y-m-d').'.csv';
        $bureaus = self::bureausFor($viewer);

        return response()->streamDownload(function () use ($query, $viewer, $bureaus): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::columns($bureaus));

            $query->clone()
                ->with(collect($bureaus)->map(fn (array $bureau): string => $bureau[0])->values()->all())
                ->withCount('journeySessions')
                ->orderByDesc('customers.id')
                ->lazy(200)
                ->each(fn (Customer $customer) => fputcsv($handle, array_map(self::safe(...), self::row($customer, $viewer, $bureaus))));

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The bureau columns this viewer sees in the table.
     *
     * @return array<string, array{0: string, 1: class-string<Model>}>
     */
    public static function bureausFor(?User $viewer): array
    {
        return array_filter(self::BUREAUS, fn (array $bureau): bool => (bool) $viewer?->can('viewAny', $bureau[1]));
    }

    /**
     * @param  array<string, array{0: string, 1: class-string<Model>}>  $bureaus
     * @return list<string>
     */
    public static function columns(array $bureaus): array
    {
        return ['Full name', 'Email address', 'Phone', 'Location', 'PAN (masked)', 'Portal', 'Applications', ...array_keys($bureaus), 'First seen (IST)'];
    }

    /**
     * @param  array<string, array{0: string, 1: class-string<Model>}>  $bureaus
     * @return list<mixed>
     */
    public static function row(Customer $customer, ?User $viewer, array $bureaus): array
    {
        return [
            $customer->full_name,
            $customer->email,
            $customer->phone,
            CustomerLocation::forCustomer($customer, $viewer),
            $customer->pan_masked,
            CustomerForm::portalStatus($customer),
            $customer->journey_sessions_count ?? $customer->journeySessions()->count(),
            ...array_values(array_map(fn (array $bureau): string => self::bureauCell($customer->{$bureau[0]}), $bureaus)),
            $customer->created_at?->timezone(self::TIMEZONE)->format('Y-m-d H:i'),
        ];
    }

    /** "Success, score 761", "Failed", or "Not requested". */
    private static function bureauCell(?Model $request): string
    {
        if ($request === null) {
            return 'Not requested';
        }

        return $request->status->getLabel().($request->score !== null ? ', score '.$request->score : '');
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
