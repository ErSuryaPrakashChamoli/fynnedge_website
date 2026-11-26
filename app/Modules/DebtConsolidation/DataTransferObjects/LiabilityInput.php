<?php

namespace App\Modules\DebtConsolidation\DataTransferObjects;

use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Enums\LiabilityAccountStatus;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Enums\RepaymentStructure;

/**
 * One existing liability as the engine sees it.
 *
 * outstandingPrincipal is the principal owed today, after the last paid
 * instalment and excluding anything listed as overdue or pending.
 * monthlyPayment is the EMI (amortising) or the payment the customer plans to
 * make (revolving); null when unknown. A verified foreclosure quotation, when
 * present, replaces the estimated closure amount for a full closure.
 */
final readonly class LiabilityInput
{
    public function __construct(
        public string $key,
        public LiabilityType $type,
        public string $lenderName,
        public float $outstandingPrincipal,
        public float $annualInterestRate,
        public ?float $monthlyPayment = null,
        public ?int $remainingMonths = null,
        public ?bool $secured = null,
        public ?RepaymentStructure $repaymentStructure = null,
        public LiabilityAccountStatus $accountStatus = LiabilityAccountStatus::Regular,
        public int $daysPastDue = 0,
        public float $overdueAmount = 0.0,
        public float $pendingInstalment = 0.0,
        public float $accruedInterest = 0.0,
        public float $foreclosureChargePercent = 0.0,
        public float $foreclosureChargeFixed = 0.0,
        public float $otherCharges = 0.0,
        public ?string $lockInEndsOn = null,
        public ?float $quotedClosureAmount = null,
        public ?string $quotationValidUntil = null,
        public bool $quotationVerified = false,
        public ConsolidationMode $mode = ConsolidationMode::Full,
        public ?float $partialAmount = null,
    ) {}

    public function isSecured(): bool
    {
        return $this->secured ?? $this->type->isSecuredByDefault();
    }

    public function structure(): RepaymentStructure
    {
        return $this->repaymentStructure ?? $this->type->defaultRepaymentStructure();
    }

    public function withMode(ConsolidationMode $mode, ?float $partialAmount = null): self
    {
        return new self(...[...$this->constructorArguments(), 'mode' => $mode, 'partialAmount' => $partialAmount]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: (string) $data['key'],
            type: LiabilityType::from($data['type']),
            lenderName: (string) ($data['lender_name'] ?? ''),
            outstandingPrincipal: (float) $data['outstanding_principal'],
            annualInterestRate: (float) ($data['annual_interest_rate'] ?? 0),
            monthlyPayment: self::nullableFloat($data['monthly_payment'] ?? null),
            remainingMonths: isset($data['remaining_months']) && $data['remaining_months'] !== '' ? (int) $data['remaining_months'] : null,
            secured: isset($data['secured']) ? (bool) $data['secured'] : null,
            repaymentStructure: isset($data['repayment_structure']) ? RepaymentStructure::from($data['repayment_structure']) : null,
            accountStatus: isset($data['account_status']) ? LiabilityAccountStatus::from($data['account_status']) : LiabilityAccountStatus::Regular,
            daysPastDue: (int) ($data['days_past_due'] ?? 0),
            overdueAmount: (float) ($data['overdue_amount'] ?? 0),
            pendingInstalment: (float) ($data['pending_instalment'] ?? 0),
            accruedInterest: (float) ($data['accrued_interest'] ?? 0),
            foreclosureChargePercent: (float) ($data['foreclosure_charge_percent'] ?? 0),
            foreclosureChargeFixed: (float) ($data['foreclosure_charge_fixed'] ?? 0),
            otherCharges: (float) ($data['other_charges'] ?? 0),
            lockInEndsOn: $data['lock_in_ends_on'] ?? null,
            quotedClosureAmount: self::nullableFloat($data['quoted_closure_amount'] ?? null),
            quotationValidUntil: $data['quotation_valid_until'] ?? null,
            quotationVerified: (bool) ($data['quotation_verified'] ?? false),
            mode: isset($data['mode']) ? ConsolidationMode::from($data['mode']) : ConsolidationMode::Full,
            partialAmount: self::nullableFloat($data['partial_amount'] ?? null),
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'lender_name' => $this->lenderName,
            'outstanding_principal' => $this->outstandingPrincipal,
            'annual_interest_rate' => $this->annualInterestRate,
            'monthly_payment' => $this->monthlyPayment,
            'remaining_months' => $this->remainingMonths,
            'secured' => $this->isSecured(),
            'repayment_structure' => $this->structure()->value,
            'account_status' => $this->accountStatus->value,
            'days_past_due' => $this->daysPastDue,
            'overdue_amount' => $this->overdueAmount,
            'pending_instalment' => $this->pendingInstalment,
            'accrued_interest' => $this->accruedInterest,
            'foreclosure_charge_percent' => $this->foreclosureChargePercent,
            'foreclosure_charge_fixed' => $this->foreclosureChargeFixed,
            'other_charges' => $this->otherCharges,
            'lock_in_ends_on' => $this->lockInEndsOn,
            'quoted_closure_amount' => $this->quotedClosureAmount,
            'quotation_valid_until' => $this->quotationValidUntil,
            'quotation_verified' => $this->quotationVerified,
            'mode' => $this->mode->value,
            'partial_amount' => $this->partialAmount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function constructorArguments(): array
    {
        return get_object_vars($this);
    }

    private static function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
