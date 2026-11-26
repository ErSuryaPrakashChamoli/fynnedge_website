<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\DebtConsolidation\Enums\ClosureStatus;
use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Models\Concerns\HasStatusHistory;
use Database\Factories\DebtClosureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Closure of one existing liability, full or partial. Status changes go
 * through UpdateClosureStatus, which enforces the evidence each step needs.
 */
#[Fillable([
    'debt_application_id', 'debt_liability_id', 'debt_lender_application_id', 'closure_type', 'status',
    'debt_foreclosure_quotation_id', 'final_closure_amount', 'payment_initiated_at', 'payment_confirmed_at',
    'closure_requested_at', 'noc_received_at', 'noc_document_id', 'verified_by', 'verified_at', 'notes',
])]
class DebtClosure extends Model
{
    /** @use HasFactory<DebtClosureFactory> */
    use Auditable, HasFactory, HasPublicId, HasStatusHistory;

    protected static function newFactory(): DebtClosureFactory
    {
        return DebtClosureFactory::new();
    }

    protected function casts(): array
    {
        return [
            'closure_type' => ConsolidationMode::class,
            'status' => ClosureStatus::class,
            'final_closure_amount' => 'decimal:2',
            'payment_initiated_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'closure_requested_at' => 'datetime',
            'noc_received_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DebtApplication::class, 'debt_application_id');
    }

    public function liability(): BelongsTo
    {
        return $this->belongsTo(DebtLiability::class, 'debt_liability_id');
    }

    public function lenderApplication(): BelongsTo
    {
        return $this->belongsTo(DebtLenderApplication::class, 'debt_lender_application_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(DebtForeclosureQuotation::class, 'debt_foreclosure_quotation_id');
    }

    public function nocDocument(): BelongsTo
    {
        return $this->belongsTo(DebtDocument::class, 'noc_document_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtClosurePayment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DebtDocument::class);
    }

    public function isFullClosure(): bool
    {
        return $this->closure_type !== ConsolidationMode::Partial;
    }

    public function confirmedPaymentTotal(): float
    {
        return round((float) $this->payments()->whereNotNull('confirmed_at')->sum('amount'), 2);
    }
}
