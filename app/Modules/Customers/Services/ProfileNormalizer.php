<?php

namespace App\Modules\Customers\Services;

use App\Modules\Customers\DataTransferObjects\CustomerProfile;
use App\Modules\Journey\Models\JourneySession;
use Carbon\Carbon;

class ProfileNormalizer
{
    public function normalize(JourneySession $session): CustomerProfile
    {
        $responses = $session->responsesByKey();

        $monthlyIncome = $this->toFloat($responses['monthly_income'] ?? null);
        $otherIncome = $this->toFloat($responses['other_monthly_income'] ?? null);

        $hasExistingEmis = ($responses['has_existing_emis'] ?? null) === 'yes';

        return new CustomerProfile(
            fullName: $responses['full_name'] ?? null,
            email: $responses['email'] ?? null,
            phone: $responses['phone'] ?? null,
            age: $this->age($responses['date_of_birth'] ?? null),
            city: $responses['city'] ?? null,
            employmentType: $responses['employment_type'] ?? null,
            employerName: $responses['company_name'] ?? $responses['business_name'] ?? null,
            monthlyIncome: $monthlyIncome,
            otherMonthlyIncome: $otherIncome,
            totalMonthlyIncome: ($monthlyIncome ?? 0.0) + ($otherIncome ?? 0.0),
            hasExistingEmis: $hasExistingEmis,
            existingEmiAmount: $hasExistingEmis ? ($this->toFloat($responses['existing_emi_amount'] ?? null) ?? 0.0) : 0.0,
            loanAmountRequested: $this->toFloat($responses['loan_amount'] ?? null),
            preferredTenureMonths: $this->toInt($responses['preferred_tenure_months'] ?? null),
            raw: $responses,
        );
    }

    private function toFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function toInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function age(mixed $dateOfBirth): ?int
    {
        if (! $dateOfBirth) {
            return null;
        }

        try {
            return Carbon::parse($dateOfBirth)->age;
        } catch (\Exception) {
            return null;
        }
    }
}
