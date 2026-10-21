<?php

namespace App\Modules\Customers\Services;

use App\Models\User;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilReportDocument;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxReportDocument;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianReportDocument;

/**
 * Where a person lives, for the admin CSV downloads: the customer's own city,
 * state and PIN when on file, otherwise the first address in a full bureau
 * report (CIBIL, Experian or Equifax; CRIF's parser has no address list).
 * The address is part of the report, so it is only given to staff who may
 * read that report. Nothing here calls a bureau.
 */
final class CustomerLocation
{
    /**
     * The bureau requests that can hold a full report with addresses.
     *
     * @var list<class-string<CibilCreditRequest|ExperianCreditRequest|EquifaxCreditRequest>>
     */
    private const REPORT_MODELS = [CibilCreditRequest::class, ExperianCreditRequest::class, EquifaxCreditRequest::class];

    /** "City, State PIN" from the customer's profile, or null when none is filled in. */
    public static function fromProfile(?Customer $customer): ?string
    {
        if ($customer === null) {
            return null;
        }

        $place = trim(collect([$customer->city, $customer->state])->filter()->implode(', ').' '.$customer->pincode);

        return $place === '' ? null : $place;
    }

    /** The first address the report lists, with its PIN, when the viewer may read the report. */
    public static function fromReport(CibilCreditRequest|EquifaxCreditRequest|ExperianCreditRequest|null $request, ?User $viewer): ?string
    {
        if ($request === null || ! $request->hasReport() || ! $viewer?->can('viewReport', $request)) {
            return null;
        }

        $document = match (true) {
            $request instanceof CibilCreditRequest => new CibilReportDocument($request->raw_response),
            $request instanceof EquifaxCreditRequest => new EquifaxReportDocument($request->raw_response),
            default => new ExperianReportDocument($request->raw_response),
        };

        $address = $document->addresses()[0] ?? null;

        return $address === null ? null : collect([$address['address'], $address['postal']])->filter()->implode(', ');
    }

    /** The profile location, else the newest full report the viewer may read. */
    public static function forCustomer(Customer $customer, ?User $viewer): ?string
    {
        if (($place = self::fromProfile($customer)) !== null) {
            return $place;
        }

        $newestReport = collect(self::REPORT_MODELS)
            ->filter(fn (string $model): bool => (bool) $viewer?->can('viewReport', $model))
            ->map(fn (string $model) => $model::query()
                ->where('customer_id', $customer->getKey())
                ->where('product', 'report')
                ->where('status', 'success')
                ->whereNull('purged_at')
                ->latest('completed_at')
                ->orderByDesc('id')
                ->first())
            ->filter()
            ->sortByDesc(fn ($request) => $request->completed_at)
            ->first();

        return self::fromReport($newestReport, $viewer);
    }
}
