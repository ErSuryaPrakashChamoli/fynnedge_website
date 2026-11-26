<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

/**
 * Existing-versus-proposed comparison for one scenario.
 *
 * Both totals are the cash the customer pays from today for the liabilities
 * in scope, so principal is counted exactly once on each side:
 *   existingTotalOutflow = overdue/pending dues paid now + remaining payments
 *   proposedTotalOutflow = upfront cash + new loan repayments + payments on
 *                          any balance left after a partial closure
 *   netFinancialBenefit  = existingTotalOutflow − proposedTotalOutflow
 * A lower EMI alone is never reported as a saving.
 */
final readonly class ScenarioResult
{
    /**
     * @param  list<string>  $includedKeys
     * @param  list<array<string, mixed>>  $lines
     * @param  list<array{year: int, existing: float, proposed: float, cumulative_existing: float, cumulative_proposed: float}>  $yearly
     * @param  list<FlagRaised>  $flags
     */
    public function __construct(
        public string $scenarioKey,
        public string $label,
        public float $annualInterestRate,
        public int $tenureMonths,
        public array $includedKeys,
        public float $principalClosed,
        public float $duesCleared,
        public float $closureCosts,
        public float $closureRequirement,
        public bool $closureCostsFinanced,
        public float $loanAmount,
        public float $processingFee,
        public float $processingFeeTax,
        public float $otherUpfrontCosts,
        public float $netDisbursal,
        public float $shortfall,
        public float $surplus,
        public float $newEmi,
        public float $newLoanTotalRepayment,
        public float $newLoanInterest,
        public float $existingMonthlyOutflow,
        public float $existingTotalOutflow,
        public float $existingInterest,
        public int $existingMonths,
        public float $proposedMonthlyOutflow,
        public float $proposedTotalOutflow,
        public float $proposedInterest,
        public int $proposedMonths,
        public float $upfrontCash,
        public float $residualRepayment,
        public float $monthlyCashFlowDifference,
        public float $netFinancialBenefit,
        public ?float $benefitPercent,
        public float $interestSaving,
        public float $totalConsolidationCosts,
        public int $tenureChangeMonths,
        public ?int $costBreakEvenMonth,
        public bool $costRecoveryHolds,
        public ?int $cashBreakEvenMonth,
        public float $weightedExistingRate,
        public float $rateImprovementPoints,
        public array $lines,
        public array $yearly,
        public array $flags,
    ) {}

    public function isBeneficial(): bool
    {
        return $this->netFinancialBenefit > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = get_object_vars($this);
        $data['flags'] = array_map(fn (FlagRaised $flag): array => $flag->toArray(), $this->flags);

        $snake = [];

        foreach ($data as $key => $value) {
            $snake[strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $key))] = $value;
        }

        return $snake;
    }
}
