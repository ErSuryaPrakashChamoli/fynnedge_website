<?php

namespace App\Modules\CreditScore\Services;

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\CreditScore\Actions\SubmitCreditScoreCheck;
use App\Modules\CreditScore\DataTransferObjects\RegenerationDecision;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Customers\Models\Customer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * How often a new credit report may be generated (user decision 2026-10-17):
 * every check is free, and a person gets one NEW bureau report every
 * `every_months` (3) and at most `max_per_year` (4) in any 12 months —
 * counted per customer AND per PAN, across all bureaus.
 *
 * A "new report" is a genuine check the bureau answered (score or no score),
 * not one answered from a stored score; failed, uncertain and demo checks do
 * not count. Inside the limit, a check on a bureau the same person already
 * has a recent report from shows that saved score again (no new pull); any
 * other check is refused before the bureau is asked or the OTP is spent.
 *
 * Nothing in the Surepass agreement sets this limit: it is the business's
 * own rule, which also caps per-transaction bureau costs.
 */
class CreditReportRegenerationLimit
{
    public static function everyMonths(): int
    {
        return max(1, (int) config('customer_portal.regeneration.every_months'));
    }

    public static function maxPerYear(): int
    {
        return max(1, (int) config('customer_portal.regeneration.max_per_year'));
    }

    public function decide(?Customer $customer, BureauName $bureau, string $pan, string $mobileNumber): RegenerationDecision
    {
        $pan = PanNumber::normalize($pan);

        if (! SubmitCreditScoreCheck::isLive($bureau) || SubmitCreditScoreCheck::wouldAnswerFromStore($bureau, $pan, $mobileNumber)) {
            return new RegenerationDecision(RegenerationDecision::ALLOWED);
        }

        $reports = $this->recentNewReports($customer, $pan);
        $nextAt = $this->nextAllowedAt($reports);

        if ($nextAt === null || ! $nextAt->isFuture()) {
            return new RegenerationDecision(RegenerationDecision::ALLOWED);
        }

        // Only a report from the bureau that answers this page now: its saved score
        // is what live() will show. Another bureau's report never stands in for it.
        $source = SubmitCreditScoreCheck::sourceBureau($bureau);
        $saved = $reports->first(fn (CreditScoreCheck $report): bool => $report->bureau === $bureau
            && $report->sourceBureau() === $source
            && $report->pan_number === $pan
            && $report->mobile_number === $mobileNumber);

        if ($saved !== null) {
            return new RegenerationDecision(RegenerationDecision::SAVED_SCORE, $nextAt, $saved, $saved->completed_at);
        }

        $own = $customer !== null ? $reports->first(fn (CreditScoreCheck $report): bool => $report->customer_id === $customer->getKey()) : null;

        return $own !== null
            ? new RegenerationDecision(RegenerationDecision::BLOCKED, $nextAt, $own)
            : new RegenerationDecision(RegenerationDecision::BLOCKED_FOR_PAN);
    }

    /**
     * When this customer can next get a new report from any bureau; null when
     * they can now. Counts the customer's own reports and those for the PAN
     * on their profile.
     */
    public function nextNewReportAt(Customer $customer): ?CarbonInterface
    {
        $nextAt = $this->nextAllowedAt($this->recentNewReports($customer, $customer->pan()));

        return $nextAt?->isFuture() ? $nextAt : null;
    }

    /**
     * New reports for this customer or PAN in the last 12 months, newest first.
     *
     * @return Collection<int, CreditScoreCheck>
     */
    private function recentNewReports(?Customer $customer, ?string $pan): Collection
    {
        if ($customer === null && blank($pan)) {
            return new Collection;
        }

        return CreditScoreCheck::query()
            ->genuine()
            ->where('status', CreditCheckStatus::Completed)
            ->whereNull('raw_response->reused_from')
            ->where('completed_at', '>', now()->subYear())
            ->where(fn (Builder $query) => $query
                ->when($customer !== null, fn (Builder $query) => $query->orWhere('customer_id', $customer->getKey()))
                ->when(filled($pan), fn (Builder $query) => $query->orWhere('pan_number', PanNumber::normalize($pan))))
            ->latest('completed_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, CreditScoreCheck>  $reports  newest first
     */
    private function nextAllowedAt(Collection $reports): ?CarbonInterface
    {
        if ($reports->isEmpty()) {
            return null;
        }

        $afterGap = $reports->first()->completed_at->copy()->addMonths(self::everyMonths());
        $limitReached = $reports->get(self::maxPerYear() - 1);
        $afterYear = $limitReached?->completed_at->copy()->addYear();

        return $afterYear !== null && $afterYear->greaterThan($afterGap) ? $afterYear : $afterGap;
    }
}
