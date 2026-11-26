<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Enums\FinancingRoute;

/**
 * A proposed consolidation loan. Rate and fees are assumptions for comparison
 * — FynnEdge does not set lender rates; a lender's sanction replaces them.
 *
 * feesDeductedFromDisbursal: the lender deducts the processing fee (+GST)
 * from the amount paid out, so the loan is grossed up to still cover the
 * closures. closureCostsFinanced: foreclosure charges are paid out of the new
 * loan (otherwise the customer pays them upfront). liabilityOverrides lets a
 * scenario close a different set of liabilities than the case default.
 */
final readonly class ScenarioInput
{
    /**
     * @param  array<string, array{mode: string, partial_amount?: float|null}>  $liabilityOverrides
     */
    public function __construct(
        public string $key,
        public string $label,
        public float $annualInterestRate,
        public int $tenureMonths,
        public float $processingFeePercent = 0.0,
        public float $processingFeeFixed = 0.0,
        public float $otherUpfrontCosts = 0.0,
        public bool $feesDeductedFromDisbursal = true,
        public bool $closureCostsFinanced = true,
        public ?float $loanAmountOverride = null,
        public ?FinancingRoute $route = null,
        public array $liabilityOverrides = [],
    ) {}

    public function applyTo(LiabilityInput $liability): LiabilityInput
    {
        $override = $this->liabilityOverrides[$liability->key] ?? null;

        if ($override === null) {
            return $liability;
        }

        return $liability->withMode(
            ConsolidationMode::from($override['mode']),
            isset($override['partial_amount']) ? (float) $override['partial_amount'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: (string) $data['key'],
            label: (string) ($data['label'] ?? ''),
            annualInterestRate: (float) $data['annual_interest_rate'],
            tenureMonths: (int) $data['tenure_months'],
            processingFeePercent: (float) ($data['processing_fee_percent'] ?? 0),
            processingFeeFixed: (float) ($data['processing_fee_fixed'] ?? 0),
            otherUpfrontCosts: (float) ($data['other_upfront_costs'] ?? 0),
            feesDeductedFromDisbursal: (bool) ($data['fees_deducted_from_disbursal'] ?? true),
            closureCostsFinanced: (bool) ($data['closure_costs_financed'] ?? true),
            loanAmountOverride: isset($data['loan_amount_override']) && $data['loan_amount_override'] !== '' ? (float) $data['loan_amount_override'] : null,
            route: isset($data['route']) ? FinancingRoute::tryFrom((string) $data['route']) : null,
            liabilityOverrides: (array) ($data['liability_overrides'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'annual_interest_rate' => $this->annualInterestRate,
            'tenure_months' => $this->tenureMonths,
            'processing_fee_percent' => $this->processingFeePercent,
            'processing_fee_fixed' => $this->processingFeeFixed,
            'other_upfront_costs' => $this->otherUpfrontCosts,
            'fees_deducted_from_disbursal' => $this->feesDeductedFromDisbursal,
            'closure_costs_financed' => $this->closureCostsFinanced,
            'loan_amount_override' => $this->loanAmountOverride,
            'route' => $this->route?->value,
            'liability_overrides' => $this->liabilityOverrides,
        ];
    }
}
