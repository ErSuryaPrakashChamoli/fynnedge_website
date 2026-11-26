<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Modules\DebtConsolidation\DataTransferObjects\CaseAssessment;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Services\CaseInputBuilder;
use App\Modules\DebtConsolidation\Services\Finance\DebtConsolidationEngine;

/**
 * Runs the engine on a case's current data and stores the result as an
 * immutable snapshot (inputs, results, formula and scoring versions, input
 * hash). Every figure a report, the portal or an advisor screen shows comes
 * from one of these snapshots.
 */
class AssessDebtApplication
{
    public function __construct(
        private readonly CaseInputBuilder $builder,
        private readonly DebtConsolidationEngine $engine,
    ) {}

    public function handle(DebtApplication $application, ActorType $actor, ?int $userId = null): DebtCalculationSnapshot
    {
        [$case, $scoringConfig] = $this->builder->forApplication($application);
        $assessment = $this->engine->assess($case);
        $recommended = $assessment->scenario();

        $snapshot = $application->snapshots()->create([
            'kind' => DebtCalculationSnapshot::KIND_ASSESSMENT,
            'formula_version' => $assessment->formulaVersion,
            'scoring_config_id' => $scoringConfig?->getKey(),
            'scoring_version' => $case->scoringConfig->version,
            'inputs' => $case->toArray(),
            'inputs_hash' => $case->hash(),
            'results' => $assessment->toArray(),
            'total_outstanding' => $assessment->portfolio->totalOutstanding,
            'net_benefit' => $recommended['result']->netFinancialBenefit ?? $this->bestBenefit($assessment),
            'suitability_score' => $recommended['suitability']->score ?? null,
            'suitability_band' => $recommended['suitability']->band ?? null,
            'created_by_type' => $actor,
            'created_by' => $userId,
        ]);

        $application->recordEvent('assessment_calculated', [
            'snapshot' => $snapshot->public_id,
            'formula_version' => $assessment->formulaVersion,
            'scoring_version' => $case->scoringConfig->version,
        ], $userId);

        return $snapshot;
    }

    private function bestBenefit(CaseAssessment $assessment): ?float
    {
        $benefits = array_map(fn (array $row): float => $row['result']->netFinancialBenefit, $assessment->scenarios);

        return $benefits === [] ? null : max($benefits);
    }
}
