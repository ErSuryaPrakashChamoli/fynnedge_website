<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

/**
 * The customer's whole debt position today, every liability included.
 */
final readonly class PortfolioSummary
{
    /**
     * @param  list<array{type: string, count: int, outstanding: float, share: ?float}>  $byType
     * @param  list<array{lender: string, count: int, outstanding: float, share: ?float}>  $byLender
     * @param  array<string, array{count: int, outstanding: float}>  $maturityProfile
     */
    public function __construct(
        public int $liabilityCount,
        public float $totalOutstanding,
        public float $totalMonthlyObligation,
        public float $weightedAverageRate,
        public float $highestRate,
        public float $securedOutstanding,
        public float $unsecuredOutstanding,
        public ?float $securedShare,
        public float $totalOverdue,
        public int $liabilitiesInArrears,
        public float $remainingRepayment,
        public float $remainingInterest,
        public ?float $largestLenderShare,
        public ?float $concentrationIndex,
        public array $byType,
        public array $byLender,
        public array $maturityProfile,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'liability_count' => $this->liabilityCount,
            'total_outstanding' => $this->totalOutstanding,
            'total_monthly_obligation' => $this->totalMonthlyObligation,
            'weighted_average_rate' => $this->weightedAverageRate,
            'highest_rate' => $this->highestRate,
            'secured_outstanding' => $this->securedOutstanding,
            'unsecured_outstanding' => $this->unsecuredOutstanding,
            'secured_share' => $this->securedShare,
            'total_overdue' => $this->totalOverdue,
            'liabilities_in_arrears' => $this->liabilitiesInArrears,
            'remaining_repayment' => $this->remainingRepayment,
            'remaining_interest' => $this->remainingInterest,
            'largest_lender_share' => $this->largestLenderShare,
            'concentration_index' => $this->concentrationIndex,
            'by_type' => $this->byType,
            'by_lender' => $this->byLender,
            'maturity_profile' => $this->maturityProfile,
        ];
    }
}
