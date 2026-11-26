<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtLenderMatch;
use App\Modules\DebtConsolidation\Models\DebtLenderPolicy;
use App\Modules\DebtConsolidation\Services\LenderMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Re-assesses the case (a fresh snapshot, so matching uses today's data) and
 * compares it with every lender debt consolidation policy in force. One
 * match row per lender offer is kept up to date; an advisor's review stays
 * attached unless the outcome changes. Matches for offers whose policy is no
 * longer in force are removed unless an advisor already reviewed them.
 */
class MatchLenders
{
    public function __construct(
        private readonly AssessDebtApplication $assess,
        private readonly LenderMatcher $matcher,
    ) {}

    /**
     * @return Collection<int, DebtLenderMatch>
     */
    public function handle(DebtApplication $application, ?int $userId = null): Collection
    {
        $snapshot = $this->assess->handle($application, $userId === null ? ActorType::System : ActorType::Staff, $userId);
        $policies = DebtLenderPolicy::query()->inForce()->with('lenderProduct.lender', 'lenderProduct.loanProduct')->orderByDesc('version')->get()
            ->unique('lender_product_id');

        return DB::transaction(function () use ($application, $snapshot, $policies, $userId): Collection {
            $matches = new Collection;

            foreach ($policies as $policy) {
                $result = $this->matcher->evaluate($application, $snapshot, $policy);
                $match = DebtLenderMatch::query()->firstOrNew(['debt_application_id' => $application->getKey(), 'lender_product_id' => $policy->lender_product_id]);
                $outcomeChanged = $match->exists && $match->match_status !== $result['status'];

                $match->fill([
                    'debt_lender_policy_id' => $policy->getKey(),
                    'debt_calculation_snapshot_id' => $snapshot->getKey(),
                    'match_status' => $result['status'],
                    'indicative_loan_amount' => $result['loan_amount'],
                    'indicative_rate_min' => $result['rate_min'],
                    'indicative_rate_max' => $result['rate_max'],
                    'reasons' => $result['reasons'],
                    'missing_information' => $result['missing'],
                    'eligibility_status' => $result['eligibility_status'],
                    'evaluated_at' => now(),
                ]);

                if ($outcomeChanged) {
                    $match->fill(['advisor_status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null]);
                }

                $match->save();
                $matches->push($match);
            }

            $application->lenderMatches()
                ->whereNotIn('lender_product_id', $policies->pluck('lender_product_id'))
                ->where('advisor_status', 'pending')
                ->delete();

            $application->recordEvent('lenders_matched', ['policies' => $policies->count(), 'snapshot' => $snapshot->public_id], $userId);

            return $matches;
        });
    }
}
