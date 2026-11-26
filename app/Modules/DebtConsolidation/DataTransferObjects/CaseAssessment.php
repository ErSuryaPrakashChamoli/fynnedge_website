<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

/**
 * The full result of assessing a case. toArray() is what a snapshot stores as
 * its results; reports and screens read only that array.
 */
final readonly class CaseAssessment
{
    /**
     * @param  array<string, array{classification: LiabilityClassification, projection: LiabilityProjection, full_closure: ?ForeclosureEstimate}>  $liabilities
     * @param  list<array{scenario: ScenarioInput, result: ScenarioResult, affordability: AffordabilityResult, suitability: SuitabilityResult}>  $scenarios
     * @param  list<FlagRaised>  $flags
     */
    public function __construct(
        public string $formulaVersion,
        public PortfolioSummary $portfolio,
        public AffordabilityResult $currentAffordability,
        public array $liabilities,
        public array $scenarios,
        public array $flags,
        public ?string $recommendedScenarioKey,
    ) {}

    /**
     * @return array{scenario: ScenarioInput, result: ScenarioResult, affordability: AffordabilityResult, suitability: SuitabilityResult}|null
     */
    public function scenario(?string $key = null): ?array
    {
        $key ??= $this->recommendedScenarioKey;

        foreach ($this->scenarios as $scenario) {
            if ($key === null || $scenario['scenario']->key === $key) {
                return $scenario;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'formula_version' => $this->formulaVersion,
            'portfolio' => $this->portfolio->toArray(),
            'current_affordability' => $this->currentAffordability->toArray(),
            'liabilities' => array_map(fn (array $row): array => [
                'classification' => $row['classification']->toArray(),
                'projection' => $row['projection']->summary(),
                'full_closure' => $row['full_closure']?->toArray(),
            ], $this->liabilities),
            'scenarios' => array_map(fn (array $row): array => [
                'scenario' => $row['scenario']->toArray(),
                'result' => $row['result']->toArray(),
                'affordability' => $row['affordability']->toArray(),
                'suitability' => $row['suitability']->toArray(),
            ], $this->scenarios),
            'flags' => array_map(fn (FlagRaised $flag): array => $flag->toArray(), $this->flags),
            'recommended_scenario_key' => $this->recommendedScenarioKey,
        ];
    }
}
