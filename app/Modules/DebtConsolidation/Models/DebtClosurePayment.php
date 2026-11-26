<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\DebtConsolidation\Enums\ClosurePaymentMode;
use Database\Factories\DebtClosurePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment towards closing a liability (usually paid by the new lender
 * directly to the old one). confirmed_at is set only when staff have seen
 * evidence the old lender received it.
 */
#[Fillable([
    'debt_closure_id', 'amount', 'paid_on', 'payment_mode', 'paid_by', 'utr_reference', 'debt_document_id',
    'confirmed_at', 'confirmed_by', 'notes', 'recorded_by',
])]
class DebtClosurePayment extends Model
{
    /** @use HasFactory<DebtClosurePaymentFactory> */
    use Auditable, HasFactory, HasPublicId;

    public const PAID_BY_NEW_LENDER = 'new_lender';

    public const PAID_BY_CUSTOMER = 'customer';

    protected static function newFactory(): DebtClosurePaymentFactory
    {
        return DebtClosurePaymentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'payment_mode' => ClosurePaymentMode::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function closure(): BelongsTo
    {
        return $this->belongsTo(DebtClosure::class, 'debt_closure_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DebtDocument::class, 'debt_document_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
