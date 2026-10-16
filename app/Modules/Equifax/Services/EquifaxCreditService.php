<?php

namespace App\Modules\Equifax\Services;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Actions\RequestEquifaxCheck;
use App\Modules\Equifax\DataTransferObjects\EquifaxCheckRequest;
use App\Modules\Equifax\DataTransferObjects\EquifaxOutcome;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Models\EquifaxCreditRequest;

/**
 * The one entry point other modules (loan processing, eligibility, future
 * features) use for Equifax inside this application — no HTTP round trip to
 * our own API, and no Surepass knowledge outside this module.
 *
 * Reading is FREE: latest(), latestScore() and summary() only read stored
 * results and never trigger a paid call, so they are safe on every page load.
 * Only request() can spend money, and it goes through RequestEquifaxCheck's
 * permission, consent, duplicate and reuse rules like every other caller.
 */
class EquifaxCreditService
{
    public function __construct(private readonly RequestEquifaxCheck $requester) {}

    /**
     * Ask for a score or report as $actor; may reuse, block or queue.
     */
    public function request(EquifaxCheckRequest $request, User $actor): EquifaxOutcome
    {
        return $this->requester->handle($request, $actor);
    }

    /**
     * The customer's newest successful result whose contents are still held.
     * A score request is answered by a report too; a report only by a report.
     */
    public function latest(Customer $customer, EquifaxProduct $product = EquifaxProduct::Report, bool $reusableOnly = false): ?EquifaxCreditRequest
    {
        return EquifaxCreditRequest::query()
            ->where('customer_id', $customer->id)
            ->when(
                $reusableOnly,
                fn ($query) => $query->reusableFor($product),
                fn ($query) => $query
                    ->where('status', EquifaxRequestStatus::Success)
                    ->whereNull('purged_at')
                    ->when($product === EquifaxProduct::Report, fn ($query) => $query->where('product', EquifaxProduct::Report)),
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
        $latest = $this->latest($customer, EquifaxProduct::Score);

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
        $latest = $this->latest($customer, EquifaxProduct::Report);

        return $latest === null ? null : [
            ...($latest->summary ?? []),
            'fetched_at' => $latest->completed_at?->toIso8601String(),
            'reusable' => $latest->isReusable(),
            'request_id' => $latest->public_id,
        ];
    }
}
