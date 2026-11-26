<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

/**
 * What keeping a liability (or what is left of it) costs from today.
 * monthlyObligation is the current monthly outgo used for affordability
 * (FOIR/DSCR); effectiveAnnualRate is the rate the projection used (the rate
 * implied by EMI × remaining months when both were given).
 */
final readonly class LiabilityProjection
{
    /**
     * @param  list<FlagRaised>  $flags
     */
    public function __construct(
        public CashFlowSchedule $schedule,
        public float $monthlyObligation,
        public float $effectiveAnnualRate,
        public ?float $impliedAnnualRate = null,
        public bool $assumed = false,
        public array $flags = [],
    ) {}

    public static function empty(): self
    {
        return new self(new CashFlowSchedule, 0.0, 0.0);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'monthly_obligation' => $this->monthlyObligation,
            'effective_annual_rate' => round($this->effectiveAnnualRate, 2),
            'implied_annual_rate' => $this->impliedAnnualRate === null ? null : round($this->impliedAnnualRate, 2),
            'remaining_months' => $this->schedule->months(),
            'remaining_repayment' => $this->schedule->total(),
            'remaining_interest' => $this->schedule->totalInterest(),
            'repayment_assumed' => $this->assumed,
        ];
    }
}
