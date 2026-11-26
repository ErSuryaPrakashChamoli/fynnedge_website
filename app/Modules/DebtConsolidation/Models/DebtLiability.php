<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Lender;
use App\Models\User;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Enums\LiabilityAccountStatus;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Enums\RepaymentStructure;
use App\Modules\DebtConsolidation\Enums\VerificationStatus;
use Database\Factories\DebtLiabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An existing debt the customer (or staff) recorded. Figures are entered
 * manually — there is no OCR or document extraction — and are "unverified"
 * until staff check them against the lender's statement.
 */
#[Fillable([
    'debt_application_id', 'liability_type', 'lender_id', 'lender_name', 'account_reference', 'account_reference_masked',
    'is_secured', 'repayment_structure', 'outstanding_principal', 'interest_rate', 'rate_basis', 'monthly_payment',
    'remaining_months', 'account_status', 'days_past_due', 'overdue_amount', 'pending_instalment', 'accrued_interest',
    'foreclosure_charge_percent', 'foreclosure_charge_fixed', 'other_charges', 'lock_in_ends_on', 'consolidation_mode',
    'partial_amount', 'notes', 'verification_status', 'verified_by', 'verified_at', 'sort_order',
])]
#[Hidden(['account_reference'])]
class DebtLiability extends Model
{
    /** @use HasFactory<DebtLiabilityFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected static function newFactory(): DebtLiabilityFactory
    {
        return DebtLiabilityFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $liability): void {
            if ($liability->isDirty('account_reference')) {
                $reference = preg_replace('/\s+/', '', (string) $liability->account_reference);
                $liability->account_reference_masked = $reference === '' ? null : 'XXXX'.substr($reference, -4);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'liability_type' => LiabilityType::class,
            'account_reference' => 'encrypted',
            'is_secured' => 'boolean',
            'repayment_structure' => RepaymentStructure::class,
            'outstanding_principal' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'monthly_payment' => 'decimal:2',
            'account_status' => LiabilityAccountStatus::class,
            'overdue_amount' => 'decimal:2',
            'pending_instalment' => 'decimal:2',
            'accrued_interest' => 'decimal:2',
            'foreclosure_charge_percent' => 'decimal:4',
            'foreclosure_charge_fixed' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'lock_in_ends_on' => 'date',
            'consolidation_mode' => ConsolidationMode::class,
            'partial_amount' => 'decimal:2',
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['account_reference', 'account_reference_masked'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DebtApplication::class, 'debt_application_id');
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(DebtForeclosureQuotation::class)->latest('id');
    }

    public function closure(): HasOne
    {
        return $this->hasOne(DebtClosure::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DebtDocument::class);
    }

    /**
     * The newest quotation staff recorded for this liability, verified or not;
     * the engine decides whether it is still current.
     */
    public function currentQuotation(): ?DebtForeclosureQuotation
    {
        return $this->relationLoaded('quotations') ? $this->quotations->first() : $this->quotations()->first();
    }

    public function toEngineInput(): LiabilityInput
    {
        $quotation = $this->currentQuotation();

        return new LiabilityInput(
            key: $this->public_id,
            type: $this->liability_type,
            lenderName: $this->lender_name,
            outstandingPrincipal: (float) $this->outstanding_principal,
            annualInterestRate: (float) $this->interest_rate,
            monthlyPayment: $this->monthly_payment === null ? null : (float) $this->monthly_payment,
            remainingMonths: $this->remaining_months,
            secured: $this->is_secured,
            repaymentStructure: $this->repayment_structure,
            accountStatus: $this->account_status ?? LiabilityAccountStatus::Regular,
            daysPastDue: (int) $this->days_past_due,
            overdueAmount: (float) $this->overdue_amount,
            pendingInstalment: (float) $this->pending_instalment,
            accruedInterest: (float) $this->accrued_interest,
            foreclosureChargePercent: (float) $this->foreclosure_charge_percent,
            foreclosureChargeFixed: (float) $this->foreclosure_charge_fixed,
            otherCharges: (float) $this->other_charges,
            lockInEndsOn: $this->lock_in_ends_on?->toDateString(),
            quotedClosureAmount: $quotation === null ? null : (float) $quotation->total_amount,
            quotationValidUntil: $quotation?->valid_until?->toDateString(),
            quotationVerified: $quotation?->verification_status === VerificationStatus::Verified,
            mode: $this->consolidation_mode ?? ConsolidationMode::Full,
            partialAmount: $this->partial_amount === null ? null : (float) $this->partial_amount,
        );
    }
}
