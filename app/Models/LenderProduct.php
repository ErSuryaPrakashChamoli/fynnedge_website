<?php

namespace App\Models;

use App\Enums\LenderStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\LenderProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lender_id', 'loan_product_id', 'min_amount', 'max_amount',
    'min_tenure_months', 'max_tenure_months', 'interest_rate_from',
    'interest_rate_to', 'processing_fee_note', 'status',
])]
class LenderProduct extends Model
{
    /** @use HasFactory<LenderProductFactory> */
    use HasFactory, HasPublicId;

    protected function casts(): array
    {
        return [
            'status' => LenderStatus::class,
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'interest_rate_from' => 'decimal:2',
            'interest_rate_to' => 'decimal:2',
        ];
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }
}
