<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\DebtConsolidation\Enums\VerificationStatus;
use Database\Factories\DebtForeclosureQuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A foreclosure (closure amount) quotation from the existing lender. It is
 * used by the engine instead of the estimate, and counts as verified only
 * after staff checked it against the lender's letter and while it is valid.
 */
#[Fillable([
    'debt_liability_id', 'quoted_on', 'valid_until', 'principal_outstanding', 'accrued_interest', 'foreclosure_charges',
    'tax_amount', 'overdue_amount', 'other_charges', 'total_amount', 'lender_reference', 'debt_document_id',
    'verification_status', 'verified_by', 'verified_at', 'notes', 'recorded_by',
])]
class DebtForeclosureQuotation extends Model
{
    /** @use HasFactory<DebtForeclosureQuotationFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected static function newFactory(): DebtForeclosureQuotationFactory
    {
        return DebtForeclosureQuotationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'quoted_on' => 'date',
            'valid_until' => 'date',
            'principal_outstanding' => 'decimal:2',
            'accrued_interest' => 'decimal:2',
            'foreclosure_charges' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'overdue_amount' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function liability(): BelongsTo
    {
        return $this->belongsTo(DebtLiability::class, 'debt_liability_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DebtDocument::class, 'debt_document_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }
}
