<?php

namespace App\Modules\Wallet\Models;

use App\Models\Concerns\HasPublicId;
use App\Modules\Customers\Models\Customer;
use App\Modules\Wallet\Enums\PlanType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'plan', 'starts_at', 'ends_at', 'price_paise', 'gst_paise', 'wallet_transaction_id'])]
class CustomerPlan extends Model
{
    use HasPublicId;

    protected function casts(): array
    {
        return [
            'plan' => PlanType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'price_paise' => 'integer',
            'gst_paise' => 'integer',
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

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }
}
