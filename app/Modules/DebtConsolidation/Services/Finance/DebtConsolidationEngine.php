<?php

namespace App\Modules\DebtConsolidation\Services\Finance;

use App\Modules\DebtConsolidation\DataTransferObjects\AffordabilityResult;
use App\Modules\DebtConsolidation\DataTransferObjects\CaseAssessment;
use App\Modules\DebtConsolidation\DataTransferObjects\CaseInput;
use App\Modules\DebtConsolidation\DataTransferObjects\FlagRaised;
use App\Modules\DebtConsolidation\DataTransferObjects\IndividualFinances;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioResult;
use App\Modules\DebtConsolidation\DataTransferObjects\SuitabilityResult;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Enums\RiskFlag;
use App\Modules\DebtConsolidation\Enums\SuitabilityBand;

/**
 * The single entry point for every debt consolidation figure — public
 * calculators, the application journey, advisor screens and reports all call
 * assess(). It is pure: no database, no clock, no config reads.
 *
 * Liabilities that cannot be consolidated now (non-performing, settled,
 * written off, disputed, 90+ DPD, inside a foreclosure lock-in) are kept out
 * of every scenario even if marked for closure, and flagged; they still count
 * in the portfolio and in affordability.
 */
final class DebtConsolidationEngine
{
    public function __construct(
        private readonly LiabilityProjector $projector = new LiabilityProjector,
        private readonly ForeclosureEstimator $foreclosure = new ForeclosureEstimator,
        private readonly ConsolidationComparator $comparator = new ConsolidationComparator,
        private readonly AffordabilityCalculator $affordability = new AffordabilityCalculator,
        private readonly PortfolioAnalyser $portfolio = new PortfolioAnalyser,
        private readonly SuitabilityScorer $scorer = new SuitabilityScorer,
        private readonly LiabilityClassifier $classifier = new LiabilityClassifier,
    ) {}

    public function assess(CaseInput $case): CaseAssessment
    {
        $assumptions = $case->assumptions;
        $fallbackMonths = $case->scenarios[0]->tenureMonths ?? $assumptions->defaultTenureMonths;
        $liabilities = [];
        $projections = [];
        $usable = [];
        $caseFlags = [];
        $inArrears = 0;

        foreach ($case->liabilities as $liability) {
            $classification = $this->classifier->classify($liability, $case->applicantType, $case->routeLiabilityTypes, $assumptions);
            $projection = $this->projector->project($liability, $fallbackMonths, $assumptions);
            $projections[$liability->key] = $projection;

            $liabilities[$liability->key] = [
                'classification' => $classification,
                'projection' => $projection,
                'full_closure' => $this->foreclosure->estimate($liability->withMode(ConsolidationMode::Full), $assumptions),
            ];

            $caseFlags = [...$caseFlags, ...$classification->blockers, ...$projection->flags];
            $inArrears += ($liability->overdueAmount > 0 || $liability->daysPastDue > 0) ? 1 : 0;
            $blocking = array_filter($classification->blockers, fn (FlagRaised $flag): bool => $flag->flag !== RiskFlag::NoEligibleRoute);
            $usable[] = $blocking === [] ? $liability : $liability->withMode(ConsolidationMode::Exclude);
        }

        if ($inArrears > 0) {
            $caseFlags[] = new FlagRaised(RiskFlag::OverdueLiabilities, null, ['count' => $inArrears]);
        }

        $currentDebtService = Money::sum(array_map(fn ($projection): float => $projection->monthlyObligation, $projections));
        $currentAffordability = $this->affordabilityFor($case, $currentDebtService, $currentDebtService);
        $scenarios = [];

        foreach ($case->scenarios as $scenario) {
            $result = $this->comparator->compare($usable, $scenario, $assumptions);
            $kept = 0.0;

            foreach ($usable as $liability) {
                if (! in_array($liability->key, $result->includedKeys, true)) {
                    $kept += $projections[$liability->key]->monthlyObligation;
                }
            }

            $affordability = $this->affordabilityFor($case, $currentDebtService, Money::round($kept + $result->proposedMonthlyOutflow));
            $flags = $this->unique([...$caseFlags, ...$result->flags, ...$affordability->flags]);

            $scenarios[] = [
                'scenario' => $scenario,
                'result' => $result,
                'affordability' => $affordability,
                'suitability' => $this->scorer->score($result, $affordability, $case->scoringConfig, $flags),
            ];
        }

        return new CaseAssessment(
            formulaVersion: FormulaVersion::CURRENT,
            portfolio: $this->portfolio->summarise($case->liabilities, $projections),
            currentAffordability: $currentAffordability,
            liabilities: $liabilities,
            scenarios: $scenarios,
            flags: $this->unique([...$caseFlags, ...$currentAffordability->flags]),
            recommendedScenarioKey: $this->recommended($scenarios),
        );
    }

    private function affordabilityFor(CaseInput $case, float $current, float $proposed): AffordabilityResult
    {
        if ($case->applicantType === ApplicantType::Business && $case->businessFinances !== null) {
            return $this->affordability->forBusiness($case->businessFinances, $current, $proposed, $case->scoringConfig);
        }

        return $this->affordability->forIndividual($case->individualFinances ?? new IndividualFinances(null), $current, $proposed, $case->scoringConfig);
    }

    /**
     * The best-scoring scenario that actually saves money; none when no
     * scenario is beneficial.
     *
     * @param  list<array{scenario: ScenarioInput, result: ScenarioResult, affordability: AffordabilityResult, suitability: SuitabilityResult}>  $scenarios
     */
    private function recommended(array $scenarios): ?string
    {
        $best = null;

        foreach ($scenarios as $row) {
            if ($row['suitability']->band === SuitabilityBand::NotBeneficial) {
                continue;
            }

            if ($best === null || $row['suitability']->score > $best['suitability']->score
                || ($row['suitability']->score === $best['suitability']->score && $row['result']->netFinancialBenefit > $best['result']->netFinancialBenefit)) {
                $best = $row;
            }
        }

        return $best['scenario']->key ?? null;
    }

    /**
     * @param  list<FlagRaised>  $flags
     * @return list<FlagRaised>
     */
    private function unique(array $flags): array
    {
        $seen = [];

        foreach ($flags as $flag) {
            $seen[$flag->flag->value.'|'.$flag->liabilityKey] ??= $flag;
        }

        return array_values($seen);
    }
}
