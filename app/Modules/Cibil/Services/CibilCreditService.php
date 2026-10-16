<?php

namespace App\Modules\Cibil\Services;

use App\Models\User;
use App\Modules\Cibil\Actions\RequestCibilCheck;
use App\Modules\Cibil\DataTransferObjects\CibilCheckRequest;
use App\Modules\Cibil\DataTransferObjects\CibilOutcome;
use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Enums\CibilRequestStatus;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Customers\Models\Customer;

/**
 * The one entry point other modules (loan processing, eligibility, future
 * features) use for CIBIL inside this application — no HTTP round trip to
 * our own API, and no Surepass knowledge outside this module.
 *
 * Reading is FREE: latest(), latestScore() and summary() only read stored
 * results and never trigger a paid call, so they are safe on every page load.
 * Only request() can spend money, and it goes through RequestCibilCheck's
 * permission, consent, duplicate and reuse rules like every other caller.
 */
class CibilCreditService
{
    public function __construct(private readonly RequestCibilCheck $requester) {}

    /**
     * Ask for a score, report or PDF as $actor; may reuse, block or queue.
     */
    public function request(CibilCheckRequest $request, User $actor): CibilOutcome
    {
        return $this->requester->handle($request, $actor);
    }

    /**
     * The customer's newest successful result whose contents are still held.
     * Asked for a score, any product answers (reports and PDFs carry the
     * score); asked for a report or PDF, only that product does.
     */
    public function latest(Customer $customer, CibilProduct $product = CibilProduct::Report, bool $reusableOnly = false): ?CibilCreditRequest
    {
        return CibilCreditRequest::query()
            ->where('customer_id', $customer->id)
            ->when(
                $reusableOnly,
                fn ($query) => $query->reusableFor($product),
                fn ($query) => $query
                    ->where('status', CibilRequestStatus::Success)
                    ->whereNull('purged_at')
                    ->when($product !== CibilProduct::Score, fn ($query) => $query->where('product', $product)),
            )
            ->latest('completed_at')
            ->latest('id')
            ->first();
    }

    /**
     * @return array{score: ?int, product: string, fetched_at: ?string, reusable: bool, request_id: string}|null
     */
    public function latestScore(Customer $customer): ?array
    {
        $latest = $this->latest($customer, CibilProduct::Score);

        return $latest === null ? null : [
            'score' => $latest->score,
            'product' => $latest->product->value,
            'fetched_at' => $latest->completed_at?->toIso8601String(),
            'reusable' => $latest->isReusable(),
            'request_id' => $latest->public_id,
        ];
    }

    /**
     * The stored headline figures of the customer's newest full report — what
     * loan processing needs without opening the report.
     *
     * @return array<string, int|string|null>|null
     */
    public function summary(Customer $customer): ?array
    {
        $latest = $this->latest($customer, CibilProduct::Report);

        return $latest === null ? null : [
            ...($latest->summary ?? []),
            'fetched_at' => $latest->completed_at?->toIso8601String(),
            'reusable' => $latest->isReusable(),
            'request_id' => $latest->public_id,
        ];
    }
}
