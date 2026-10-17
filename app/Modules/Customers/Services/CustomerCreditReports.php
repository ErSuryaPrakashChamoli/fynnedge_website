<?php

namespace App\Modules\Customers\Services;

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Actions\SubmitCreditScoreCheck;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\CreditScore\Services\CreditReportRegenerationLimit;
use App\Modules\Customers\DataTransferObjects\RefreshEligibility;
use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * A customer's own credit-score history, read-only. Everything here comes
 * from checks already saved against the account — nothing here ever calls a
 * bureau, so signing in, opening the dashboard or viewing a report costs
 * nothing.
 *
 * Only genuine checks are listed (a real bureau answered — never a demo
 * score), each bureau separately: scores from different bureaus are never
 * merged, averaged or ranked against each other.
 *
 * A check answered from a stored score inside the reuse period (raw_response
 * reused_from) is a repeat view, not a new report: it counts as an attempt
 * but is not listed as a report of its own.
 */
class CustomerCreditReports
{
    public function __construct(private readonly CreditReportRegenerationLimit $limit) {}

    /**
     * Every bureau answer and attempt, newest first — without repeat views.
     *
     * @return Builder<CreditScoreCheck>
     */
    public function history(Customer $customer): Builder
    {
        return $this->attempts($customer)->whereNull('raw_response->reused_from');
    }

    /**
     * Every genuine check the customer ran, repeat views included.
     *
     * @return Builder<CreditScoreCheck>
     */
    public function attempts(Customer $customer): Builder
    {
        return CreditScoreCheck::query()
            ->whereBelongsTo($customer)
            ->genuine()
            ->latest('requested_at')
            ->orderByDesc('id');
    }

    /**
     * The customer's check with this public id — or null, which callers turn
     * into a 404, for anyone else's check, a demo check or an unknown id.
     */
    public function find(Customer $customer, string $publicId): ?CreditScoreCheck
    {
        return CreditScoreCheck::query()
            ->whereBelongsTo($customer)
            ->genuine()
            ->where('public_id', $publicId)
            ->first();
    }

    /**
     * The newest successful check per bureau, in BureauName order.
     *
     * @return Collection<int, CreditScoreCheck>
     */
    public function latestSuccessful(Customer $customer): Collection
    {
        return new Collection(collect(BureauName::cases())
            ->map(fn (BureauName $bureau): ?CreditScoreCheck => $this->latestSuccessFor($customer, $bureau))
            ->filter()
            ->values()
            ->all());
    }

    /**
     * The headline check for the dashboard: the newest successful check that
     * holds a score, whichever bureau it came from (its bureau is always shown
     * with it).
     */
    public function latestScore(Customer $customer): ?CreditScoreCheck
    {
        return $this->history($customer)
            ->where('status', CreditCheckStatus::Completed)
            ->whereNotNull('score')
            ->reorder()
            ->latest('completed_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, RefreshEligibility> keyed by bureau value
     */
    public function refreshOverview(Customer $customer): array
    {
        return collect(BureauName::cases())
            ->mapWithKeys(fn (BureauName $bureau): array => [$bureau->value => $this->refreshEligibility($customer, $bureau)])
            ->all();
    }

    /**
     * A new report is available once both the bureau's reuse period and the
     * regeneration limit (one new report every 3 months, at most 4 a year,
     * across all bureaus — CreditReportRegenerationLimit) allow it.
     */
    public function refreshEligibility(Customer $customer, BureauName $bureau): RefreshEligibility
    {
        $reuseDays = SubmitCreditScoreCheck::reuseDays($bureau);
        $latestSuccess = $this->latestSuccessFor($customer, $bureau);
        $latestAttempt = $this->attempts($customer)->where('bureau', $bureau)->first();
        $nextEligibleAt = collect([
            $latestSuccess?->completed_at?->copy()->addDays($reuseDays),
            $this->limit->nextNewReportAt($customer),
        ])->filter()->max();

        $inProgress = $this->attempts($customer)
            ->where('bureau', $bureau)
            ->where('status', CreditCheckStatus::Pending)
            ->where('created_at', '>=', now()->subMinutes(SubmitCreditScoreCheck::uncertainHoldMinutes()))
            ->exists();

        $state = match (true) {
            ! SubmitCreditScoreCheck::isLive($bureau) => RefreshEligibility::UNAVAILABLE,
            $inProgress => RefreshEligibility::IN_PROGRESS,
            $nextEligibleAt !== null && $nextEligibleAt->isFuture() => RefreshEligibility::TOO_SOON,
            default => RefreshEligibility::AVAILABLE,
        };

        return new RefreshEligibility($bureau, $state, $reuseDays, $latestSuccess, $latestAttempt, $nextEligibleAt);
    }

    private function latestSuccessFor(Customer $customer, BureauName $bureau): ?CreditScoreCheck
    {
        return $this->history($customer)
            ->where('bureau', $bureau)
            ->where('status', CreditCheckStatus::Completed)
            ->reorder()
            ->latest('completed_at')
            ->orderByDesc('id')
            ->first();
    }
}
