<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

/**
 * Monthly finances of an individual. otherMonthlyObligations are fixed
 * commitments that are not liabilities in the case (e.g. a guarantee or a
 * loan the customer chose not to list); rent and living costs go in
 * monthlyLivingExpenses and only affect disposable income, not FOIR.
 */
final readonly class IndividualFinances
{
    public function __construct(
        public ?float $netMonthlyIncome,
        public float $otherMonthlyIncome = 0.0,
        public ?float $monthlyLivingExpenses = null,
        public float $otherMonthlyObligations = 0.0,
    ) {}

    public function totalIncome(): ?float
    {
        if ($this->netMonthlyIncome === null || $this->netMonthlyIncome <= 0) {
            return null;
        }

        return $this->netMonthlyIncome + max(0.0, $this->otherMonthlyIncome);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            netMonthlyIncome: isset($data['net_monthly_income']) ? (float) $data['net_monthly_income'] : null,
            otherMonthlyIncome: (float) ($data['other_monthly_income'] ?? 0),
            monthlyLivingExpenses: isset($data['monthly_living_expenses']) ? (float) $data['monthly_living_expenses'] : null,
            otherMonthlyObligations: (float) ($data['other_monthly_obligations'] ?? 0),
        );
    }

    /**
     * @return array<string, float|null>
     */
    public function toArray(): array
    {
        return [
            'net_monthly_income' => $this->netMonthlyIncome,
            'other_monthly_income' => $this->otherMonthlyIncome,
            'monthly_living_expenses' => $this->monthlyLivingExpenses,
            'other_monthly_obligations' => $this->otherMonthlyObligations,
        ];
    }
}
