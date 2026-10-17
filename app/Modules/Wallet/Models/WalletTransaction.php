<?php

namespace App\Modules\Wallet\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Wallet\Enums\WalletTransactionStatus;
use App\Modules\Wallet\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line of a customer's wallet ledger, in paise (credits positive, debits
 * negative). Written only through App\Modules\Wallet\Services\Wallet, which
 * locks the customer row so the balance cannot go below zero.
 */
#[Fillable([
    'customer_id', 'type', 'status', 'amount_paise', 'taxable_paise', 'gst_paise',
    'description', 'reference_type', 'reference_id', 'created_by', 'settled_at',
])]
class WalletTransaction extends Model
{
    use HasPublicId;

    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'status' => WalletTransactionStatus::class,
            'amount_paise' => 'integer',
            'taxable_paise' => 'integer',
            'gst_paise' => 'integer',
            'settled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
