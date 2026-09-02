<?php

namespace App\Modules\Eligibility\Services;

use App\Enums\LenderStatus;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Services\AnalyticsEventDispatcher;
use App\Modules\Customers\DataTransferObjects\CustomerProfile;
use App\Modules\Customers\Services\ProfileNormalizer;
use App\Modules\Eligibility\DataTransferObjects\EligibilityEvaluation;
use App\Modules\Eligibility\Enums\EligibilityStatus;
use App\Modules\Eligibility\Models\EligibilityResult;
use App\Modules\Eligibility\Models\Employer;
use App\Modules\Eligibility\Models\EmployerRating;
use App\Modules\Journey\Models\JourneySession;
use Illuminate\Support\Collection;

class EligibilityEngine
{
    public function __construct(
        private readonly ProfileNormalizer $normalizer,
        private readonly FoirCalculator $foirCalculator,
        private readonly RuleGroupEvaluator $ruleGroupEvaluator,
        private readonly AnalyticsEventDispatcher $analytics,
    ) {}

    /**
     * Evaluates and persists a result against every active lender product for the
     * session's loan product. Safe to call more than once — re-evaluating updates
     * the existing result rather than accumulating duplicates.
     *
     * @return Collection<int, EligibilityResult>
     */
    public function evaluateSession(JourneySession $session): Collection
    {
        $profile = $this->normalizer->normalize($session);

        $lenderProducts = $session->loanProduct->lenderProducts()
            ->where('status', LenderStatus::Active)
            ->with('lender')
            ->get();

        return $lenderProducts->map(
            fn (LenderProduct $lenderProduct) => $this->evaluateAndPersist($session, $lenderProduct, $profile),
        );
    }

    /**
     * The pure, non-persisting core — used both internally and by the admin rule tester,
     * which supplies a hand-entered attribute bag instead of a real journey session.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function evaluateAttributes(array $attributes, LenderProduct $lenderProduct): EligibilityEvaluation
    {
        if (! array_key_exists('employer_category', $attributes)) {
            $attributes['employer_category'] = $this->resolveEmployerCategory(
                $attributes['employer_name'] ?? null,
                $lenderProduct->lender_id,
            );
        }

        $foir = $this->foirCalculator->calculate(
            $this->numeric($attributes['loan_amount_requested'] ?? null),
            $this->numeric($attributes['total_monthly_income'] ?? null),
            $this->numeric($attributes['existing_emi_amount'] ?? null) ?? 0.0,
            isset($attributes['preferred_tenure_months']) ? (int) $attributes['preferred_tenure_months'] : null,
            $lenderProduct,
        );

        $attributes = [...$attributes, 'foir' => $foir];

        $ruleSet = $lenderProduct->activeEligibilityRuleSet();
        $reasons = [];
        $eligible = (bool) $ruleSet;

        if ($ruleSet) {
            foreach ($ruleSet->rules as $rule) {
                $passed = $this->ruleGroupEvaluator->evaluate($rule, $attributes);

                $reasons[] = [
                    'eligibility_rule_id' => $rule->id,
                    'label' => $rule->label,
                    'priority' => $rule->priority,
                    'passed' => $passed,
                    'customer_message' => $rule->customer_message,
                ];

                if (! $passed && $rule->priority->blocksEligibility()) {
                    $eligible = false;
                }
            }
        }

        return new EligibilityEvaluation(
            status: $eligible ? EligibilityStatus::Eligible : EligibilityStatus::NotEligible,
            foir: $foir,
            reasons: $reasons,
        );
    }

    /**
     * Evaluates a hand-entered attribute bag against every active lender for one loan
     * product, without touching a journey session — this is what the admin rule tester uses.
     *
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, array{lenderProduct: LenderProduct, evaluation: EligibilityEvaluation}>
     */
    public function evaluateAttributesForLoanProduct(array $attributes, LoanProduct $loanProduct): Collection
    {
        return $loanProduct->lenderProducts()
            ->where('status', LenderStatus::Active)
            ->with('lender')
            ->get()
            ->map(fn (LenderProduct $lenderProduct) => [
                'lenderProduct' => $lenderProduct,
                'evaluation' => $this->evaluateAttributes($attributes, $lenderProduct),
            ]);
    }

    private function evaluateAndPersist(JourneySession $session, LenderProduct $lenderProduct, CustomerProfile $profile): EligibilityResult
    {
        $attributes = $this->buildAttributes($profile);
        $evaluation = $this->evaluateAttributes($attributes, $lenderProduct);

        $result = EligibilityResult::query()->updateOrCreate(
            ['journey_session_id' => $session->id, 'lender_product_id' => $lenderProduct->id],
            [
                'eligibility_rule_set_id' => $lenderProduct->activeEligibilityRuleSet()?->id,
                'status' => $evaluation->status,
                'foir' => $evaluation->foir,
                'evaluated_at' => now(),
            ],
        );

        $result->reasons()->delete();

        foreach ($evaluation->reasons as $reason) {
            $result->reasons()->create($reason);
        }

        $this->analytics->track(
            AnalyticsEventKey::EligibilityEvaluated,
            session: $session,
            lenderProduct: $lenderProduct,
            properties: ['status' => $evaluation->status->value],
        );

        return $result->load(['reasons', 'lenderProduct.lender']);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttributes(CustomerProfile $profile): array
    {
        return [
            ...$profile->raw,
            'age' => $profile->age,
            'city' => $profile->city,
            'employment_type' => $profile->employmentType,
            'employer_name' => $profile->employerName,
            'monthly_income' => $profile->monthlyIncome,
            'total_monthly_income' => $profile->totalMonthlyIncome,
            'has_existing_emis' => $profile->hasExistingEmis,
            'existing_emi_amount' => $profile->existingEmiAmount,
            'loan_amount_requested' => $profile->loanAmountRequested,
            'preferred_tenure_months' => $profile->preferredTenureMonths,
            'credit_score' => $profile->creditScore,
        ];
    }

    private function resolveEmployerCategory(?string $employerName, int $lenderId): ?string
    {
        if (! $employerName) {
            return null;
        }

        $employer = Employer::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($employerName)])->first();

        if (! $employer) {
            return null;
        }

        return EmployerRating::query()
            ->where('employer_id', $employer->id)
            ->where('lender_id', $lenderId)
            ->first()?->category?->key;
    }

    private function numeric(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
