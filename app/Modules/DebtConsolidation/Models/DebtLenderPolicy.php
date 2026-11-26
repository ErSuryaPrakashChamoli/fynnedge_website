<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\LenderProduct;
use App\Models\User;
use App\Modules\DebtConsolidation\Enums\FinancingRoute;
use App\Modules\DebtConsolidation\Enums\LenderPolicyStatus;
use Database\Factories\DebtLenderPolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A lender offer's debt consolidation criteria, as published or communicated
 * by the lender. Versioned and effective-dated, and always traceable to its
 * source (name, link/reference, date received). Only an Active policy inside
 * its effective dates is used for matching.
 */
#[Fillable([
    'lender_product_id', 'version', 'status', 'financing_route', 'applicant_types', 'accepted_liability_types',
    'excluded_existing_lender_ids', 'accepts_own_loans', 'min_loan_amount', 'max_loan_amount', 'min_monthly_income',
    'min_business_vintage_months', 'min_annual_turnover', 'min_credit_score', 'max_foir_percent', 'min_dscr',
    'max_days_past_due', 'requires_collateral', 'accepted_collateral_types', 'max_ltv_percent', 'interest_rate_min',
    'interest_rate_max', 'min_tenure_months', 'max_tenure_months', 'processing_fee_percent', 'foreclosure_restrictions',
    'required_document_type_ids', 'source_name', 'source_url', 'source_reference', 'source_received_on', 'effective_from',
    'effective_until', 'notes', 'created_by', 'approved_by', 'approved_at',
])]
class DebtLenderPolicy extends Model
{
    /** @use HasFactory<DebtLenderPolicyFactory> */
    use Auditable, HasFactory, HasPublicId, SoftDeletes;

    /**
     * Once approved, a policy's criteria are fixed: changes go into a new
     * version. Only approval, retirement and their stamps may change.
     */
    protected static function booted(): void
    {
        static::updating(function (self $policy): void {
            $changed = array_diff(array_keys($policy->getDirty()), ['status', 'approved_by', 'approved_at', 'updated_at']);

            if ($policy->getOriginal('status') !== LenderPolicyStatus::Draft && $changed !== []) {
                throw new LogicException('Only a draft lender policy can be changed. Create a new version instead.');
            }
        });
    }

    protected static function newFactory(): DebtLenderPolicyFactory
    {
        return DebtLenderPolicyFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => LenderPolicyStatus::class,
            'financing_route' => FinancingRoute::class,
            'applicant_types' => 'array',
            'accepted_liability_types' => 'array',
            'excluded_existing_lender_ids' => 'array',
            'accepts_own_loans' => 'boolean',
            'min_loan_amount' => 'decimal:2',
            'max_loan_amount' => 'decimal:2',
            'min_monthly_income' => 'decimal:2',
            'min_annual_turnover' => 'decimal:2',
            'max_foir_percent' => 'decimal:2',
            'min_dscr' => 'decimal:2',
            'requires_collateral' => 'boolean',
            'accepted_collateral_types' => 'array',
            'max_ltv_percent' => 'decimal:2',
            'interest_rate_min' => 'decimal:4',
            'interest_rate_max' => 'decimal:4',
            'processing_fee_percent' => 'decimal:4',
            'required_document_type_ids' => 'array',
            'source_received_on' => 'date',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function lenderProduct(): BelongsTo
    {
        return $this->belongsTo(LenderProduct::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Active policies in force on $date (India time), for active offers of
     * active lenders.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInForce(Builder $query, ?Carbon $date = null): void
    {
        $day = ($date ?? now('Asia/Kolkata'))->toDateString();

        $query->where('status', LenderPolicyStatus::Active)
            ->whereDate('effective_from', '<=', $day)
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $day))
            ->whereHas('lenderProduct', fn (Builder $query) => $query->where('status', 'active')
                ->whereHas('lender', fn (Builder $query) => $query->where('status', 'active')));
    }

    public function label(): string
    {
        $product = $this->lenderProduct;

        return trim(($product?->lender?->name ?? 'Lender').' — '.($product?->loanProduct?->name ?? 'Offer').' (v'.$this->version.')');
    }
}
