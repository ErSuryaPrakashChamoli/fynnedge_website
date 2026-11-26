<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\LenderProduct;
use App\Models\User;
use App\Modules\DebtConsolidation\Enums\LenderApplicationStatus;
use App\Modules\DebtConsolidation\Models\Concerns\HasStatusHistory;
use Database\Factories\DebtLenderApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An application FynnEdge facilitated to one lender for a case. Its status
 * is the lender's, recorded by staff; a case may have several.
 */
#[Fillable([
    'debt_application_id', 'lender_product_id', 'lender_name', 'product_name', 'debt_lender_match_id', 'debt_consent_id',
    'status', 'lender_reference', 'applied_amount', 'sanctioned_amount', 'sanctioned_rate', 'sanctioned_tenure_months',
    'processing_fee_amount', 'disbursed_amount', 'submitted_at', 'decision_at', 'sanctioned_at', 'disbursed_at',
    'rejection_reason', 'notes', 'created_by',
])]
class DebtLenderApplication extends Model
{
    /** @use HasFactory<DebtLenderApplicationFactory> */
    use Auditable, HasFactory, HasPublicId, HasStatusHistory;

    protected static function newFactory(): DebtLenderApplicationFactory
    {
        return DebtLenderApplicationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => LenderApplicationStatus::class,
            'applied_amount' => 'decimal:2',
            'sanctioned_amount' => 'decimal:2',
            'sanctioned_rate' => 'decimal:4',
            'processing_fee_amount' => 'decimal:2',
            'disbursed_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'decision_at' => 'datetime',
            'sanctioned_at' => 'datetime',
            'disbursed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DebtApplication::class, 'debt_application_id');
    }

    public function lenderProduct(): BelongsTo
    {
        return $this->belongsTo(LenderProduct::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(DebtLenderMatch::class, 'debt_lender_match_id');
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(DebtConsent::class, 'debt_consent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closures(): HasMany
    {
        return $this->hasMany(DebtClosure::class);
    }

    public function label(): string
    {
        return $this->lender_name.' — '.$this->product_name;
    }
}
