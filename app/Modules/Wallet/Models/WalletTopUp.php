<?php

namespace App\Modules\Wallet\Models;

use App\Models\Concerns\HasPublicId;
use App\Modules\Customers\Models\Customer;
use App\Modules\Wallet\Enums\TopUpStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'amount_paise', 'gateway', 'gateway_order_id', 'gateway_payment_id', 'status', 'paid_at', 'wallet_transaction_id'])]
class WalletTopUp extends Model
{
    use HasPublicId;

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'status' => TopUpStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }
}
