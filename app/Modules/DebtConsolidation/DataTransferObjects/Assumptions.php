<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

/**
 * Every assumption a calculation depends on, stored with each snapshot so a
 * report can be reproduced exactly. The engine never reads a clock or config
 * itself: the as-of date and these values always come in from outside.
 */
final readonly class Assumptions
{
    public function __construct(
        public string $asOf,
        public float $gstRatePercent = 18.0,
        public float $revolvingMinimumPaymentPercent = 5.0,
        public int $maxProjectionMonths = 480,
        public int $defaultTenureMonths = 60,
        public float $rateMismatchTolerancePoints = 1.5,
        public int $nonPerformingDaysPastDue = 90,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            asOf: (string) $data['as_of'],
            gstRatePercent: (float) ($data['gst_rate_percent'] ?? 18.0),
            revolvingMinimumPaymentPercent: (float) ($data['revolving_minimum_payment_percent'] ?? 5.0),
            maxProjectionMonths: (int) ($data['max_projection_months'] ?? 480),
            defaultTenureMonths: (int) ($data['default_tenure_months'] ?? 60),
            rateMismatchTolerancePoints: (float) ($data['rate_mismatch_tolerance_points'] ?? 1.5),
            nonPerformingDaysPastDue: (int) ($data['non_performing_days_past_due'] ?? 90),
        );
    }

    /**
     * @return array<string, scalar>
     */
    public function toArray(): array
    {
        return [
            'as_of' => $this->asOf,
            'gst_rate_percent' => $this->gstRatePercent,
            'revolving_minimum_payment_percent' => $this->revolvingMinimumPaymentPercent,
            'max_projection_months' => $this->maxProjectionMonths,
            'default_tenure_months' => $this->defaultTenureMonths,
            'rate_mismatch_tolerance_points' => $this->rateMismatchTolerancePoints,
            'non_performing_days_past_due' => $this->nonPerformingDaysPastDue,
        ];
    }
}
