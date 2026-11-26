<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

use App\Modules\DebtConsolidation\Services\Finance\Money;

/**
 * Month-by-month payments and the interest charged in each month. Index 0 is
 * month 1. Payments in a month may be below the interest charged (a revolving
 * balance paid below its interest); the unpaid interest is then capitalised
 * and settled later in the schedule.
 */
final readonly class CashFlowSchedule
{
    /**
     * @param  list<float>  $payments
     * @param  list<float>  $interest
     */
    public function __construct(
        public array $payments = [],
        public array $interest = [],
    ) {}

    public function months(): int
    {
        return count($this->payments);
    }

    public function total(): float
    {
        return Money::sum($this->payments);
    }

    public function totalInterest(): float
    {
        return Money::sum($this->interest);
    }

    public function paymentAt(int $month): float
    {
        return $this->payments[$month - 1] ?? 0.0;
    }

    public function interestAt(int $month): float
    {
        return $this->interest[$month - 1] ?? 0.0;
    }

    public function firstPayment(): float
    {
        return $this->payments[0] ?? 0.0;
    }

    public function plus(self $other): self
    {
        $months = max($this->months(), $other->months());
        $payments = [];
        $interest = [];

        for ($month = 1; $month <= $months; $month++) {
            $payments[] = Money::round($this->paymentAt($month) + $other->paymentAt($month));
            $interest[] = Money::round($this->interestAt($month) + $other->interestAt($month));
        }

        return new self($payments, $interest);
    }

    /**
     * Payments per year of the schedule, for charts and reports.
     *
     * @return list<float>
     */
    public function yearlyPayments(): array
    {
        return array_values(array_map(
            fn (array $year): float => Money::sum($year),
            array_chunk($this->payments, 12),
        ));
    }
}
