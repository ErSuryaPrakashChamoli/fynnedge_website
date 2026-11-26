<?php

namespace App\Modules\DebtConsolidation\Services;

use App\Models\LenderProduct;
use App\Modules\DebtConsolidation\DataTransferObjects\CaseInput;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioInput;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Enums\LenderMatchStatus;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Models\DebtCollateral;
use App\Modules\DebtConsolidation\Models\DebtLenderPolicy;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use App\Modules\DebtConsolidation\Services\Finance\DebtConsolidationEngine;
use App\Modules\Eligibility\Services\EligibilityEngine;

/**
 * Compares one case with one lender offer's debt consolidation policy, rule
 * by rule, and explains the outcome. Rules evaluated:
 *
 *  applicant type · liability types the lender takes over · existing lenders
 *  it will not take over (incl. its own loans) · loan amount range · income /
 *  business vintage / turnover minimums · declared credit score · FOIR or
 *  DSCR after consolidation on the policy's indicative terms · days past due
 *  · collateral and LTV · and, when the offer has an active eligibility rule
 *  set in Loan Products & Lenders, that rule set (reused as-is).
 *
 * Outcome: any failed rule → Does not match; otherwise any rule that could
 * not be checked → More information needed; otherwise Potential match. A
 * potential match only means the case fits the criteria as recorded; the
 * lender alone decides.
 */
class LenderMatcher
{
    public function __construct(
        private readonly DebtConsolidationEngine $engine,
        private readonly EligibilityEngine $eligibility,
    ) {}

    /**
     * @return array{status: LenderMatchStatus, reasons: list<array{code: string, passed: bool, message: string}>, missing: list<string>, loan_amount: ?float, rate_min: ?float, rate_max: ?float, eligibility_status: ?string}
     */
    public function evaluate(DebtApplication $application, DebtCalculationSnapshot $snapshot, DebtLenderPolicy $policy): array
    {
        $application->loadMissing(['liabilities', 'financialProfile', 'collaterals']);
        $case = $snapshot->caseInput();
        $profile = $application->financialProfile;
        $reasons = [];
        $missing = [];
        $pass = function (string $code, bool $passed, string $message) use (&$reasons): void {
            $reasons[] = ['code' => $code, 'passed' => $passed, 'message' => $message];
        };

        // 1. Applicant type.
        $acceptsType = in_array($application->applicant_type->value, (array) $policy->applicant_types, true);
        $pass('applicant_type', $acceptsType, $acceptsType
            ? "Accepts {$application->applicant_type->value} applicants."
            : "Does not lend to {$application->applicant_type->value} applicants.");

        // 2. Liability types and existing lenders, for the debts being consolidated.
        $included = collect($case->liabilities)->filter(fn (LiabilityInput $liability): bool => $liability->mode !== ConsolidationMode::Exclude);
        $refused = $included->reject(fn (LiabilityInput $liability): bool => in_array($liability->type->value, (array) $policy->accepted_liability_types, true));
        $pass('liability_types', $refused->isEmpty(), $refused->isEmpty()
            ? 'Takes over every type of debt being consolidated.'
            : 'Does not take over: '.$refused->map(fn (LiabilityInput $liability): string => $liability->type->getLabel())->unique()->implode(', ').'.');

        $lenderIds = $application->liabilities
            ->filter(fn (DebtLiability $liability): bool => $included->contains(fn (LiabilityInput $input): bool => $input->key === $liability->public_id))
            ->pluck('lender_id')->filter()->unique();
        $excluded = $lenderIds->intersect((array) ($policy->excluded_existing_lender_ids ?? []));
        $ownLender = $policy->lenderProduct?->lender_id;
        $ownLoans = ! $policy->accepts_own_loans && $ownLender !== null && $lenderIds->contains($ownLender);
        $pass('existing_lenders', $excluded->isEmpty() && ! $ownLoans, match (true) {
            $ownLoans => 'Does not take over its own existing loans.',
            $excluded->isNotEmpty() => 'Does not take over loans from one or more of the existing lenders.',
            default => 'No restriction on the existing lenders.',
        });

        // 3. Days past due.
        if ($policy->max_days_past_due !== null) {
            $worst = (int) $included->max(fn (LiabilityInput $liability): int => $liability->daysPastDue);
            $pass('days_past_due', $worst <= $policy->max_days_past_due, "Highest days past due {$worst}; policy allows up to {$policy->max_days_past_due}.");
        }

        // 4. Amount and affordability on the policy's indicative terms.
        $rateMin = $policy->interest_rate_min !== null ? (float) $policy->interest_rate_min : $this->offerRate($policy->lenderProduct, 'from');
        $rateMax = $policy->interest_rate_max !== null ? (float) $policy->interest_rate_max : $this->offerRate($policy->lenderProduct, 'to');
        $loanAmount = null;

        if ($included->isNotEmpty() && ($rateMin !== null || $rateMax !== null)) {
            $rate = $rateMin !== null && $rateMax !== null ? round(($rateMin + $rateMax) / 2, 4) : (float) ($rateMin ?? $rateMax);
            $tenure = (int) ($policy->max_tenure_months ?? $policy->lenderProduct?->max_tenure_months ?? $case->assumptions->defaultTenureMonths);
            $assessment = $this->engine->assess(new CaseInput(
                $case->applicantType, $case->liabilities,
                [new ScenarioInput('policy-'.$policy->public_id, $policy->label(), $rate, max(1, $tenure), (float) ($policy->processing_fee_percent ?? 0), route: $policy->financing_route)],
                $case->assumptions, $case->scoringConfig, $case->routeLiabilityTypes, $case->individualFinances, $case->businessFinances,
            ));
            $scenario = $assessment->scenarios[0];
            $loanAmount = $scenario['result']->loanAmount;
            $min = $policy->min_loan_amount ?? $policy->lenderProduct?->min_amount;
            $max = $policy->max_loan_amount ?? $policy->lenderProduct?->max_amount;
            $inRange = ($min === null || $loanAmount >= (float) $min) && ($max === null || $loanAmount <= (float) $max);
            $pass('loan_amount', $inRange, 'Indicative loan of '.DebtPresenter::inr($loanAmount).' '.($inRange ? 'is within' : 'is outside').' the policy range ('.DebtPresenter::inr($min).' – '.DebtPresenter::inr($max).').');

            $affordability = $scenario['affordability'];

            if ($affordability->metric === 'foir' && $policy->max_foir_percent !== null) {
                if ($affordability->proposedValue === null) {
                    $missing[] = 'Net monthly income (to check FOIR)';
                } else {
                    $pass('foir', $affordability->proposedValue <= (float) $policy->max_foir_percent, 'FOIR after consolidation '.DebtPresenter::percent($affordability->proposedValue).'; policy maximum '.DebtPresenter::percent($policy->max_foir_percent).'.');
                }
            }

            if ($affordability->metric === 'dscr' && $policy->min_dscr !== null) {
                if ($affordability->income === null) {
                    $missing[] = 'Business revenue and expenses (to check DSCR)';
                } else {
                    $pass('dscr', ($affordability->proposedValue ?? INF) >= (float) $policy->min_dscr, 'DSCR after consolidation '.($affordability->proposedValue ?? '∞').'×; policy minimum '.$policy->min_dscr.'×.');
                }
            }
        } else {
            $missing[] = 'Policy interest rate (to size the loan)';
        }

        // 5. Applicant minimums.
        if ($policy->min_monthly_income !== null && $application->applicant_type === ApplicantType::Individual) {
            $income = $profile?->net_monthly_income;
            if ($income === null) {
                $missing[] = 'Net monthly income';
            } else {
                $pass('income', (float) $income + (float) $profile->other_monthly_income >= (float) $policy->min_monthly_income, 'Monthly income '.DebtPresenter::inr((float) $income + (float) $profile->other_monthly_income).'; policy minimum '.DebtPresenter::inr($policy->min_monthly_income).'.');
            }
        }

        if ($policy->min_business_vintage_months !== null && $application->applicant_type === ApplicantType::Business) {
            if ($profile?->business_vintage_months === null) {
                $missing[] = 'Months in business';
            } else {
                $pass('vintage', $profile->business_vintage_months >= $policy->min_business_vintage_months, "In business {$profile->business_vintage_months} months; policy minimum {$policy->min_business_vintage_months}.");
            }
        }

        if ($policy->min_annual_turnover !== null && $application->applicant_type === ApplicantType::Business) {
            if ($profile?->annual_turnover === null) {
                $missing[] = 'Annual turnover';
            } else {
                $pass('turnover', (float) $profile->annual_turnover >= (float) $policy->min_annual_turnover, 'Annual turnover '.DebtPresenter::inr($profile->annual_turnover).'; policy minimum '.DebtPresenter::inr($policy->min_annual_turnover).'.');
            }
        }

        if ($policy->min_credit_score !== null) {
            if ($profile?->declared_credit_score === null) {
                $missing[] = 'Credit score';
            } else {
                $pass('credit_score', $profile->declared_credit_score >= $policy->min_credit_score, "Declared credit score {$profile->declared_credit_score} (not bureau-verified); policy minimum {$policy->min_credit_score}.");
            }
        }

        // 6. Collateral.
        if ($policy->requires_collateral) {
            $accepted = (array) ($policy->accepted_collateral_types ?? []);
            $usable = $application->collaterals->filter(fn (DebtCollateral $collateral): bool => $accepted === [] || in_array($collateral->collateral_type->value, $accepted, true));

            if ($usable->isEmpty()) {
                if ($application->collaterals->isEmpty()) {
                    $missing[] = 'Collateral details';
                } else {
                    $pass('collateral', false, 'None of the offered assets is a type this lender accepts.');
                }
            } else {
                $freeValue = $usable->sum(fn (DebtCollateral $collateral): float => (float) $collateral->freeValue());
                $ltv = $loanAmount !== null && $freeValue > 0 ? round($loanAmount / $freeValue * 100, 2) : null;
                $pass('collateral', $policy->max_ltv_percent === null || ($ltv !== null && $ltv <= (float) $policy->max_ltv_percent), $policy->max_ltv_percent === null
                    ? 'Acceptable collateral offered.'
                    : 'Loan-to-value '.DebtPresenter::percent($ltv).'; policy maximum '.DebtPresenter::percent($policy->max_ltv_percent).'.');
            }
        }

        // 7. The offer's existing eligibility rule set, reused unchanged.
        $eligibilityStatus = null;
        $lenderProduct = $policy->lenderProduct;

        if ($lenderProduct !== null && $lenderProduct->activeEligibilityRuleSet() !== null) {
            $kept = collect($case->liabilities)->filter(fn (LiabilityInput $liability): bool => $liability->mode === ConsolidationMode::Exclude)->sum(fn (LiabilityInput $liability): float => (float) ($liability->monthlyPayment ?? 0));
            $evaluation = $this->eligibility->evaluateAttributes(array_filter([
                'age' => $profile?->ageInYears(),
                'employment_type' => $profile?->employment_type?->value,
                'employer_name' => $profile?->employer_name,
                'monthly_income' => $profile?->net_monthly_income === null ? null : (float) $profile->net_monthly_income,
                'total_monthly_income' => $profile?->net_monthly_income === null ? null : (float) $profile->net_monthly_income + (float) $profile->other_monthly_income,
                'credit_score' => $profile?->declared_credit_score,
                'city' => $application->city,
                'loan_amount_requested' => $loanAmount,
                'preferred_tenure_months' => $policy->max_tenure_months,
                'existing_emi_amount' => $kept,
                'has_existing_emis' => $kept > 0 ? 'yes' : 'no',
                'business_vintage_years' => $profile?->business_vintage_months === null ? null : intdiv((int) $profile->business_vintage_months, 12),
                'annual_turnover' => $profile?->annual_turnover === null ? null : (float) $profile->annual_turnover,
            ], fn (mixed $value): bool => $value !== null), $lenderProduct);

            $eligibilityStatus = $evaluation->status->value;

            foreach ($evaluation->reasons as $reason) {
                if (! $reason['passed'] && $reason['priority']->blocksEligibility()) {
                    $pass('lender_rule', false, 'Lender eligibility rule not met: '.$reason['label'].'.');
                }
            }
        }

        $failed = array_filter($reasons, fn (array $reason): bool => ! $reason['passed']) !== [];

        return [
            'status' => match (true) {
                $failed => LenderMatchStatus::NotMatched,
                $missing !== [] => LenderMatchStatus::NeedsInformation,
                default => LenderMatchStatus::PotentialMatch,
            },
            'reasons' => $reasons,
            'missing' => array_values(array_unique($missing)),
            'loan_amount' => $loanAmount,
            'rate_min' => $rateMin,
            'rate_max' => $rateMax,
            'eligibility_status' => $eligibilityStatus,
        ];
    }

    private function offerRate(?LenderProduct $product, string $end): ?float
    {
        $value = $product?->{'interest_rate_'.$end};

        return $value === null ? null : (float) $value;
    }

    /**
     * Liability types the policy refuses among a set of types — for screens.
     *
     * @param  list<string>  $types
     * @return list<LiabilityType>
     */
    public static function refusedTypes(DebtLenderPolicy $policy, array $types): array
    {
        return array_values(array_filter(array_map(fn (string $type) => LiabilityType::tryFrom($type), array_diff($types, (array) $policy->accepted_liability_types))));
    }
}
