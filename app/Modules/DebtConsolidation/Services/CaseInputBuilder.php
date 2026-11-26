<?php

namespace App\Modules\DebtConsolidation\Services;

use App\Modules\DebtConsolidation\DataTransferObjects\BusinessFinances;
use App\Modules\DebtConsolidation\DataTransferObjects\CaseInput;
use App\Modules\DebtConsolidation\DataTransferObjects\IndividualFinances;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScoringConfig;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use App\Modules\DebtConsolidation\Models\DebtScenario;
use App\Modules\DebtConsolidation\Models\DebtScoringConfig;

/**
 * Turns stored case data (or calculator input) into the engine's CaseInput,
 * adding the assumptions, scoring configuration and route matrix in force.
 * The one place that decides what an assessment is calculated from.
 */
class CaseInputBuilder
{
    /**
     * @return array{0: CaseInput, 1: ?DebtScoringConfig}
     */
    public function forApplication(DebtApplication $application, ?string $asOf = null): array
    {
        $application->loadMissing(['liabilities.quotations', 'scenarios', 'financialProfile']);
        $profile = $application->financialProfile;
        $scenarios = $application->scenarios->map(fn (DebtScenario $scenario): ScenarioInput => $scenario->toEngineInput())->values()->all();
        $storedConfig = DebtScoringConfig::activeFor($application->applicant_type);

        $case = new CaseInput(
            applicantType: $application->applicant_type,
            liabilities: $application->liabilities->map(fn (DebtLiability $liability): LiabilityInput => $liability->toEngineInput())->values()->all(),
            scenarios: $scenarios !== [] ? $scenarios : $this->standardScenarios($application->applicant_type),
            assumptions: DebtConsolidationSettings::assumptions($asOf),
            scoringConfig: $storedConfig?->toScoringConfig() ?? ScoringConfig::defaults($application->applicant_type),
            routeLiabilityTypes: $this->routeMatrix(),
            individualFinances: $application->applicant_type === ApplicantType::Individual ? ($profile?->toIndividualFinances() ?? new IndividualFinances(null)) : null,
            businessFinances: $application->applicant_type === ApplicantType::Business ? ($profile?->toBusinessFinances() ?? new BusinessFinances(null, null)) : null,
        );

        return [$case, $storedConfig];
    }

    /**
     * A case built from what a visitor typed into a public calculator.
     *
     * @param  list<LiabilityInput>  $liabilities
     * @param  list<ScenarioInput>  $scenarios
     */
    public function forCalculator(ApplicantType $applicantType, array $liabilities, array $scenarios, ?IndividualFinances $individual = null, ?BusinessFinances $business = null): CaseInput
    {
        return new CaseInput(
            applicantType: $applicantType,
            liabilities: $liabilities,
            scenarios: $scenarios !== [] ? $scenarios : $this->standardScenarios($applicantType),
            assumptions: DebtConsolidationSettings::assumptions(),
            scoringConfig: DebtScoringConfig::activeFor($applicantType)?->toScoringConfig() ?? ScoringConfig::defaults($applicantType),
            routeLiabilityTypes: $this->routeMatrix(),
            individualFinances: $applicantType === ApplicantType::Individual ? ($individual ?? new IndividualFinances(null)) : null,
            businessFinances: $applicantType === ApplicantType::Business ? ($business ?? new BusinessFinances(null, null)) : null,
        );
    }

    /**
     * @return list<ScenarioInput>
     */
    public function standardScenarios(ApplicantType $applicantType): array
    {
        return array_map(fn (array $row): ScenarioInput => new ScenarioInput(
            key: 'standard-'.$row['route']->value,
            label: $row['label'],
            annualInterestRate: $row['annual_interest_rate'],
            tenureMonths: $row['tenure_months'],
            processingFeePercent: $row['processing_fee_percent'],
            route: $row['route'],
        ), DebtConsolidationSettings::standardScenarios($applicantType));
    }

    /**
     * @return array<string, list<string>>
     */
    public function routeMatrix(): array
    {
        return (array) config('debt_consolidation.route_liability_types');
    }
}
