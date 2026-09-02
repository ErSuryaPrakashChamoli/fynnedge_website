<?php

namespace App\Modules\Customers\DataTransferObjects;

/**
 * A typed, computed view over one journey session's raw responses.
 * This is what the eligibility engine (and anything else) reads instead of
 * pulling field keys out of journey_responses directly.
 */
final readonly class CustomerProfile
{
    public function __construct(
        public ?string $fullName,
        public ?string $email,
        public ?string $phone,
        public ?int $age,
        public ?string $city,
        public ?string $employmentType,
        public ?string $employerName,
        public ?float $monthlyIncome,
        public ?float $otherMonthlyIncome,
        public float $totalMonthlyIncome,
        public bool $hasExistingEmis,
        public float $existingEmiAmount,
        public ?float $loanAmountRequested,
        public ?int $preferredTenureMonths,
        public ?int $creditScore,
        /** @var array<string, mixed> raw responses, for product-specific fields (property, business, …) */
        public array $raw,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->raw[$key] ?? $default;
    }
}
