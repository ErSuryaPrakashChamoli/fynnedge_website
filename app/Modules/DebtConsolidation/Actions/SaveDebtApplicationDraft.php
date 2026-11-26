<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Enums\EmploymentType;
use App\Modules\DebtConsolidation\Enums\BusinessConstitution;
use App\Modules\DebtConsolidation\Enums\CollateralType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use App\Modules\DebtConsolidation\Services\LiabilityRows;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Saves what the customer has entered so far (save-and-continue / auto-save).
 * Only while the case is editable by the customer: before submission, or
 * while staff have asked for more information. Liabilities are synced by
 * their row key — rows removed by the customer are deleted — and a row
 * without a positive outstanding is simply not saved yet.
 */
class SaveDebtApplicationDraft
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function liabilities(DebtApplication $application, array $rows): void
    {
        $this->guard($application);

        DB::transaction(function () use ($application, $rows): void {
            $existing = $application->liabilities()->get()->keyBy('public_id');
            $kept = [];

            foreach (array_values($rows) as $order => $row) {
                $attributes = LiabilityRows::toAttributes($row);
                $key = (string) ($row['key'] ?? '');

                if ($attributes === null || ! Str::isUuid($key)) {
                    continue;
                }

                $kept[] = $key;
                $liability = $existing->get($key) ?? new DebtLiability(['debt_application_id' => $application->getKey()]);
                $liability->forceFill(['public_id' => $key, 'sort_order' => $order, ...$attributes]);

                if ($liability->isDirty()) {
                    $liability->save();
                }
            }

            $existing->reject(fn (DebtLiability $liability): bool => in_array($liability->public_id, $kept, true))
                ->each(fn (DebtLiability $liability) => $liability->delete());

            $this->touch($application);
        });
    }

    /**
     * @param  array<string, mixed>  $finances
     */
    public function finances(DebtApplication $application, array $finances): void
    {
        $this->guard($application);

        $numbers = ['net_monthly_income', 'other_monthly_income', 'monthly_living_expenses', 'other_monthly_obligations', 'average_monthly_revenue', 'monthly_operating_expenses', 'annual_turnover', 'annual_net_profit'];
        $attributes = [];

        foreach ($numbers as $field) {
            if (array_key_exists($field, $finances)) {
                $attributes[$field] = $field === 'annual_net_profit'
                    ? $this->signedMoney($finances[$field])
                    : $this->money($finances[$field], in_array($field, ['other_monthly_income', 'other_monthly_obligations'], true));
            }
        }

        $application->financialProfile()->updateOrCreate([], $attributes);
        $this->touch($application);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    public function profile(DebtApplication $application, array $profile): void
    {
        $this->guard($application);

        $application->financialProfile()->updateOrCreate([], array_filter([
            'employment_type' => EmploymentType::tryFrom((string) ($profile['employment_type'] ?? '')),
            'employer_name' => $this->text($profile['employer_name'] ?? null, 191),
            'occupation' => $this->text($profile['occupation'] ?? null, 120),
            'date_of_birth' => filled($profile['date_of_birth'] ?? null) ? $profile['date_of_birth'] : null,
            'residence_type' => $this->text($profile['residence_type'] ?? null, 20),
            'declared_credit_score' => filled($profile['declared_credit_score'] ?? null) ? (int) $profile['declared_credit_score'] : null,
            'business_name' => $this->text($profile['business_name'] ?? null, 191),
            'business_constitution' => BusinessConstitution::tryFrom((string) ($profile['business_constitution'] ?? '')),
            'industry' => $this->text($profile['industry'] ?? null, 120),
            'business_vintage_months' => filled($profile['business_vintage_months'] ?? null) ? (int) $profile['business_vintage_months'] : null,
            'gst_registered' => isset($profile['gst_registered']) && $profile['gst_registered'] !== '' ? (bool) (int) $profile['gst_registered'] : null,
            'udyam_registered' => isset($profile['udyam_registered']) && $profile['udyam_registered'] !== '' ? (bool) (int) $profile['udyam_registered'] : null,
        ], fn (mixed $value): bool => $value !== null));

        $this->touch($application);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function collaterals(DebtApplication $application, array $rows): void
    {
        $this->guard($application);

        DB::transaction(function () use ($application, $rows): void {
            $application->collaterals()->delete();

            foreach ($rows as $row) {
                $type = CollateralType::tryFrom((string) ($row['collateral_type'] ?? ''));

                if ($type === null) {
                    continue;
                }

                $application->collaterals()->create([
                    'collateral_type' => $type,
                    'description' => $this->text($row['description'] ?? null, 255),
                    'ownership' => $this->text($row['ownership'] ?? null, 20),
                    'estimated_value' => $this->money($row['estimated_value'] ?? null),
                    'existing_charge_amount' => $this->money($row['existing_charge_amount'] ?? null, true),
                    'city' => $this->text($row['city'] ?? null, 100),
                ]);
            }

            $this->touch($application);
        });
    }

    public function step(DebtApplication $application, string $step): void
    {
        if ($application->isEditableByCustomer()) {
            $application->forceFill(['journey_step' => $step, 'last_customer_activity_at' => now()])->save();
        }
    }

    private function guard(DebtApplication $application): void
    {
        if (! $application->isEditableByCustomer()) {
            throw new LogicException('This case can no longer be changed by the customer.');
        }
    }

    private function touch(DebtApplication $application): void
    {
        $application->forceFill(['last_customer_activity_at' => now()])->save();
    }

    /**
     * A business may declare a loss: kept negative, within the same bounds.
     */
    private function signedMoney(mixed $value): ?float
    {
        $clean = str_replace([',', '₹', ' '], '', (string) $value);

        return is_numeric($clean) ? round(max(-100000000000, min((float) $clean, 100000000000)), 2) : null;
    }

    private function money(mixed $value, bool $zeroWhenBlank = false): ?float
    {
        $clean = str_replace([',', '₹', ' '], '', (string) $value);

        if (! is_numeric($clean)) {
            return $zeroWhenBlank ? 0.0 : null;
        }

        return round(max(0.0, min((float) $clean, 100000000000)), 2);
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
