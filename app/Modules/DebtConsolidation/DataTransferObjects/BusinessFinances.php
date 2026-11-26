<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

/**
 * Monthly cash flow of a business. Operating expenses exclude every loan
 * repayment, so revenue minus expenses is the cash available to service debt.
 */
final readonly class BusinessFinances
{
    public function __construct(
        public ?float $averageMonthlyRevenue,
        public ?float $monthlyOperatingExpenses,
        public ?int $businessVintageMonths = null,
        public ?float $annualTurnover = null,
        public float $otherMonthlyObligations = 0.0,
    ) {}

    public function cashAvailableForDebtService(): ?float
    {
        if ($this->averageMonthlyRevenue === null || $this->monthlyOperatingExpenses === null || $this->averageMonthlyRevenue <= 0) {
            return null;
        }

        return $this->averageMonthlyRevenue - $this->monthlyOperatingExpenses;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            averageMonthlyRevenue: isset($data['average_monthly_revenue']) ? (float) $data['average_monthly_revenue'] : null,
            monthlyOperatingExpenses: isset($data['monthly_operating_expenses']) ? (float) $data['monthly_operating_expenses'] : null,
            businessVintageMonths: isset($data['business_vintage_months']) ? (int) $data['business_vintage_months'] : null,
            annualTurnover: isset($data['annual_turnover']) ? (float) $data['annual_turnover'] : null,
            otherMonthlyObligations: (float) ($data['other_monthly_obligations'] ?? 0),
        );
    }

    /**
     * @return array<string, float|int|null>
     */
    public function toArray(): array
    {
        return [
            'average_monthly_revenue' => $this->averageMonthlyRevenue,
            'monthly_operating_expenses' => $this->monthlyOperatingExpenses,
            'business_vintage_months' => $this->businessVintageMonths,
            'annual_turnover' => $this->annualTurnover,
            'other_monthly_obligations' => $this->otherMonthlyObligations,
        ];
    }
}
