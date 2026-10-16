<?php

namespace App\Modules\Experian\Services;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Experian\Actions\RequestExperianCheck;
use App\Modules\Experian\DataTransferObjects\ExperianCheckRequest;
use App\Modules\Experian\DataTransferObjects\ExperianOutcome;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Models\ExperianCreditRequest;

/**
 * The one entry point other modules (loan processing, eligibility, future
 * features) use for Experian inside this application — no HTTP round trip to
 * our own API, and no Surepass knowledge outside this module.
 *
 * Reading is FREE: latest(), latestScore() and summary() only read stored
 * results and never trigger a paid call, so they are safe on every page load.
 * Only request() can spend money, and it goes through RequestExperianCheck's
 * permission, consent, duplicate and reuse rules like every other caller.
 */
class ExperianCreditService
{
    public function __construct(private readonly RequestExperianCheck $requester) {}

    /**
     * Ask for a score, report or PDF as $actor; may reuse, block or queue.
     */
    public function request(ExperianCheckRequest $request, User $actor): ExperianOutcome
    {
        return $this->requester->handle($request, $actor);
    }

    /**
     * The customer's newest successful result whose contents are still held.
     * Asked for a score, any product answers (reports and PDFs carry the
     * score); asked for a report or PDF, only that product does.
     */
    public function latest(Customer $customer, ExperianProduct $product = ExperianProduct::Report, bool $reusableOnly = false): ?ExperianCreditRequest
    {
        return ExperianCreditRequest::query()
            ->where('customer_id', $customer->id)
            ->when(
                $reusableOnly,
                fn ($query) => $query->reusableFor($product),
                fn ($query) => $query
                    ->where('status', ExperianRequestStatus::Success)
                    ->whereNull('purged_at')
                    ->when($product !== ExperianProduct::Score, fn ($query) => $query->where('product', $product)),
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
        $latest = $this->latest($customer, ExperianProduct::Score);

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
        $latest = $this->latest($customer, ExperianProduct::Report);

        return $latest === null ? null : [
            ...($latest->summary ?? []),
            'fetched_at' => $latest->completed_at?->toIso8601String(),
            'reusable' => $latest->isReusable(),
            'request_id' => $latest->public_id,
        ];
    }
}
