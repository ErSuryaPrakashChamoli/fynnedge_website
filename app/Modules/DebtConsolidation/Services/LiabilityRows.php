<?php

namespace App\Modules\DebtConsolidation\Services;

use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioInput;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Enums\FinancingRoute;
use App\Modules\DebtConsolidation\Enums\LiabilityAccountStatus;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Enums\RepaymentStructure;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The editable loan rows of the public calculator and the application
 * journey (plain arrays of strings, as Livewire holds them): blank rows,
 * validation rules, and conversion into engine inputs. A row without a
 * positive outstanding is ignored rather than guessed at.
 */
final class LiabilityRows
{
    public const MAX_ROWS = 20;

    public const MAX_SCENARIOS = 3;

    /**
     * @return array<string, string>
     */
    public static function blank(ApplicantType $applicantType, ?LiabilityType $type = null): array
    {
        $type ??= $applicantType === ApplicantType::Business ? LiabilityType::UnsecuredBusinessLoan : LiabilityType::PersonalLoan;

        return [
            'key' => (string) Str::uuid(),
            'type' => $type->value,
            'lender_name' => '',
            'outstanding' => '',
            'rate' => '',
            'emi' => '',
            'months' => '',
            'overdue' => '',
            'days_past_due' => '',
            'pending' => '',
            'charge_percent' => '',
            'charge_fixed' => '',
            'other_charges' => '',
            'account_status' => LiabilityAccountStatus::Regular->value,
            'mode' => ConsolidationMode::Full->value,
            'partial_amount' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(string $prefix, ApplicantType $applicantType): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:10000000000'];

        return [
            $prefix => ['array', 'max:'.self::MAX_ROWS],
            "{$prefix}.*.type" => ['required', Rule::in(array_map(fn (LiabilityType $type): string => $type->value, LiabilityType::forApplicant($applicantType)))],
            "{$prefix}.*.lender_name" => ['nullable', 'string', 'max:150'],
            "{$prefix}.*.outstanding" => ['required', 'numeric', 'gt:0', 'max:10000000000'],
            "{$prefix}.*.rate" => ['nullable', 'numeric', 'min:0', 'max:60'],
            "{$prefix}.*.emi" => $money,
            "{$prefix}.*.months" => ['nullable', 'integer', 'min:1', 'max:480'],
            "{$prefix}.*.overdue" => $money,
            "{$prefix}.*.days_past_due" => ['nullable', 'integer', 'min:0', 'max:3650'],
            "{$prefix}.*.pending" => $money,
            "{$prefix}.*.charge_percent" => ['nullable', 'numeric', 'min:0', 'max:10'],
            "{$prefix}.*.charge_fixed" => $money,
            "{$prefix}.*.other_charges" => $money,
            "{$prefix}.*.account_status" => ['nullable', Rule::enum(LiabilityAccountStatus::class)],
            "{$prefix}.*.mode" => ['required', Rule::enum(ConsolidationMode::class)],
            "{$prefix}.*.partial_amount" => ['nullable', 'required_if:'.$prefix.'.*.mode,partial', 'numeric', 'gt:0', 'max:10000000000'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<LiabilityInput>
     */
    public static function toInputs(array $rows): array
    {
        $inputs = [];

        foreach (self::limit($rows) as $row) {
            $type = LiabilityType::tryFrom((string) ($row['type'] ?? ''));
            $outstanding = self::number($row['outstanding'] ?? null);

            if ($type === null || $outstanding === null || $outstanding <= 0) {
                continue;
            }

            $mode = ConsolidationMode::tryFrom((string) ($row['mode'] ?? '')) ?? ConsolidationMode::Full;

            $inputs[] = new LiabilityInput(
                key: (string) ($row['key'] ?? Str::uuid()),
                type: $type,
                lenderName: trim((string) ($row['lender_name'] ?? '')),
                outstandingPrincipal: min($outstanding, 10000000000),
                annualInterestRate: min(60.0, max(0.0, self::number($row['rate'] ?? null) ?? 0.0)),
                monthlyPayment: self::positive($row['emi'] ?? null),
                remainingMonths: ($months = self::number($row['months'] ?? null)) !== null && $months >= 1 ? (int) min(480, $months) : null,
                // Customers never set this; it always follows the chosen debt type
                // (staff can override on the stored liability).
                secured: null,
                repaymentStructure: isset($row['structure']) ? RepaymentStructure::tryFrom((string) $row['structure']) : null,
                accountStatus: LiabilityAccountStatus::tryFrom((string) ($row['account_status'] ?? '')) ?? LiabilityAccountStatus::Regular,
                daysPastDue: (int) max(0, self::number($row['days_past_due'] ?? null) ?? 0),
                overdueAmount: max(0.0, self::number($row['overdue'] ?? null) ?? 0.0),
                pendingInstalment: max(0.0, self::number($row['pending'] ?? null) ?? 0.0),
                foreclosureChargePercent: min(10.0, max(0.0, self::number($row['charge_percent'] ?? null) ?? 0.0)),
                foreclosureChargeFixed: max(0.0, self::number($row['charge_fixed'] ?? null) ?? 0.0),
                otherCharges: max(0.0, self::number($row['other_charges'] ?? null) ?? 0.0),
                mode: $mode,
                partialAmount: $mode === ConsolidationMode::Partial ? self::positive($row['partial_amount'] ?? null) : null,
            );
        }

        return $inputs;
    }

    /**
     * The first MAX_ROWS rows, re-indexed. The "add a loan" button stops at
     * MAX_ROWS, but a request can send any number of rows; everything that
     * calculates or saves goes through this so one request cannot make the
     * engine or the database work on thousands.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function limit(array $rows): array
    {
        return array_slice(array_values($rows), 0, self::MAX_ROWS);
    }

    /**
     * Database attributes of a valid row (see toInputs() for what "valid"
     * means); null for a row to ignore.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public static function toAttributes(array $row): ?array
    {
        $input = self::toInputs([$row])[0] ?? null;

        if ($input === null) {
            return null;
        }

        return [
            'liability_type' => $input->type,
            'lender_name' => $input->lenderName !== '' ? $input->lenderName : $input->type->getLabel(),
            'is_secured' => $input->isSecured(),
            'repayment_structure' => $input->structure(),
            'outstanding_principal' => $input->outstandingPrincipal,
            'interest_rate' => $input->annualInterestRate,
            'monthly_payment' => $input->monthlyPayment,
            'remaining_months' => $input->remainingMonths,
            'account_status' => $input->accountStatus,
            'days_past_due' => $input->daysPastDue,
            'overdue_amount' => $input->overdueAmount,
            'pending_instalment' => $input->pendingInstalment,
            'foreclosure_charge_percent' => $input->foreclosureChargePercent,
            'foreclosure_charge_fixed' => $input->foreclosureChargeFixed,
            'other_charges' => $input->otherCharges,
            'consolidation_mode' => $input->mode,
            'partial_amount' => $input->partialAmount,
        ];
    }

    /**
     * A stored liability as an editable row.
     *
     * @return array<string, string>
     */
    public static function fromModel(DebtLiability $liability): array
    {
        $text = fn (mixed $value): string => match (true) {
            $value === null => '',
            str_contains((string) $value, '.') => rtrim(rtrim((string) $value, '0'), '.'),
            default => (string) $value,
        };

        return [
            'key' => $liability->public_id,
            'type' => $liability->liability_type->value,
            'lender_name' => $liability->lender_name,
            'outstanding' => $text($liability->outstanding_principal),
            'rate' => $text($liability->interest_rate),
            'emi' => $text($liability->monthly_payment),
            'months' => $liability->remaining_months === null ? '' : (string) $liability->remaining_months,
            'overdue' => (float) $liability->overdue_amount > 0 ? $text($liability->overdue_amount) : '',
            'days_past_due' => $liability->days_past_due > 0 ? (string) $liability->days_past_due : '',
            'pending' => (float) $liability->pending_instalment > 0 ? $text($liability->pending_instalment) : '',
            'charge_percent' => (float) $liability->foreclosure_charge_percent > 0 ? $text($liability->foreclosure_charge_percent) : '',
            'charge_fixed' => (float) $liability->foreclosure_charge_fixed > 0 ? $text($liability->foreclosure_charge_fixed) : '',
            'other_charges' => (float) $liability->other_charges > 0 ? $text($liability->other_charges) : '',
            'account_status' => ($liability->account_status ?? LiabilityAccountStatus::Regular)->value,
            'mode' => ($liability->consolidation_mode ?? ConsolidationMode::Full)->value,
            'partial_amount' => $text($liability->partial_amount),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function blankScenario(string $label, float $rate, int $tenure, float $feePercent, ?FinancingRoute $route = null): array
    {
        return [
            'key' => (string) Str::uuid(),
            'label' => $label,
            'route' => $route?->value ?? '',
            'rate' => (string) $rate,
            'tenure' => (string) $tenure,
            'fee_percent' => (string) $feePercent,
            'fees_deducted' => '1',
            'closure_financed' => '1',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<ScenarioInput>
     */
    public static function scenarioInputs(array $rows): array
    {
        $inputs = [];

        foreach (array_slice($rows, 0, self::MAX_SCENARIOS) as $index => $row) {
            $rate = self::number($row['rate'] ?? null);
            $tenure = self::number($row['tenure'] ?? null);

            if ($rate === null || $tenure === null || $tenure < 1) {
                continue;
            }

            $inputs[] = new ScenarioInput(
                key: (string) ($row['key'] ?? 'scenario-'.$index),
                label: trim((string) ($row['label'] ?? '')) ?: __('Option :number', ['number' => $index + 1]),
                annualInterestRate: min(60.0, max(0.0, $rate)),
                tenureMonths: (int) min(360, $tenure),
                processingFeePercent: min(10.0, max(0.0, self::number($row['fee_percent'] ?? null) ?? 0.0)),
                feesDeductedFromDisbursal: (bool) (int) ($row['fees_deducted'] ?? 1),
                closureCostsFinanced: (bool) (int) ($row['closure_financed'] ?? 1),
                route: FinancingRoute::tryFrom((string) ($row['route'] ?? '')),
            );
        }

        return $inputs;
    }

    private static function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = str_replace([',', '₹', ' '], '', (string) $value);

        return is_numeric($clean) ? (float) $clean : null;
    }

    private static function positive(mixed $value): ?float
    {
        $number = self::number($value);

        return $number !== null && $number > 0 ? $number : null;
    }
}
