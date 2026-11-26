<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

use App\Modules\DebtConsolidation\Enums\ApplicantType;

/**
 * Everything one assessment depends on. toArray() is what a snapshot stores
 * as its inputs; fromArray(toArray()) reproduces the same results.
 */
final readonly class CaseInput
{
    /**
     * @param  list<LiabilityInput>  $liabilities
     * @param  list<ScenarioInput>  $scenarios
     * @param  array<string, list<string>>  $routeLiabilityTypes
     */
    public function __construct(
        public ApplicantType $applicantType,
        public array $liabilities,
        public array $scenarios,
        public Assumptions $assumptions,
        public ScoringConfig $scoringConfig,
        public array $routeLiabilityTypes,
        public ?IndividualFinances $individualFinances = null,
        public ?BusinessFinances $businessFinances = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $applicantType = ApplicantType::from($data['applicant_type']);

        return new self(
            applicantType: $applicantType,
            liabilities: array_map(fn (array $row): LiabilityInput => LiabilityInput::fromArray($row), $data['liabilities'] ?? []),
            scenarios: array_map(fn (array $row): ScenarioInput => ScenarioInput::fromArray($row), $data['scenarios'] ?? []),
            assumptions: Assumptions::fromArray($data['assumptions']),
            scoringConfig: ScoringConfig::fromArray($data['scoring_config']),
            routeLiabilityTypes: $data['route_liability_types'] ?? [],
            individualFinances: isset($data['individual_finances']) ? IndividualFinances::fromArray($data['individual_finances']) : null,
            businessFinances: isset($data['business_finances']) ? BusinessFinances::fromArray($data['business_finances']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'applicant_type' => $this->applicantType->value,
            'liabilities' => array_map(fn (LiabilityInput $liability): array => $liability->toArray(), $this->liabilities),
            'scenarios' => array_map(fn (ScenarioInput $scenario): array => $scenario->toArray(), $this->scenarios),
            'assumptions' => $this->assumptions->toArray(),
            'scoring_config' => $this->scoringConfig->toArray(),
            'route_liability_types' => $this->routeLiabilityTypes,
            'individual_finances' => $this->individualFinances?->toArray(),
            'business_finances' => $this->businessFinances?->toArray(),
        ];
    }

    public function hash(): string
    {
        return hash('sha256', (string) json_encode($this->toArray()));
    }
}
