<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

use App\Modules\DebtConsolidation\Enums\AffordabilityBand;

/**
 * Repayment affordability before and after consolidation.
 *
 * Individuals (metric "foir"): FOIR % = (EMIs and monthly debt obligations)
 * ÷ total net monthly income × 100.
 * Businesses (metric "dscr"): DSCR = cash available for debt service
 * (revenue − operating expenses) ÷ monthly debt service.
 */
final readonly class AffordabilityResult
{
    /**
     * @param  list<FlagRaised>  $flags
     */
    public function __construct(
        public string $metric,
        public ?float $income,
        public float $currentDebtService,
        public float $proposedDebtService,
        public ?float $currentValue,
        public ?float $proposedValue,
        public ?float $disposableBefore,
        public ?float $disposableAfter,
        public AffordabilityBand $currentBand,
        public AffordabilityBand $proposedBand,
        public array $flags = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'metric' => $this->metric,
            'income' => $this->income,
            'current_debt_service' => $this->currentDebtService,
            'proposed_debt_service' => $this->proposedDebtService,
            'current_value' => $this->currentValue,
            'proposed_value' => $this->proposedValue,
            'disposable_before' => $this->disposableBefore,
            'disposable_after' => $this->disposableAfter,
            'current_band' => $this->currentBand->value,
            'proposed_band' => $this->proposedBand->value,
            'flags' => array_map(fn (FlagRaised $flag): array => $flag->toArray(), $this->flags),
        ];
    }
}
