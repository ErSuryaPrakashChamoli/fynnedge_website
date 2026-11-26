<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\LenderProduct;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use Database\Factories\DebtConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A purpose-specific consent with the exact wording shown, its version and
 * hash, when and how it was given. A consent is never edited: withdrawing it
 * sets revoked_at, and giving it again creates a new row.
 */
#[Fillable([
    'debt_application_id', 'customer_id', 'purpose', 'lender_product_id', 'consent_text', 'text_version', 'text_hash',
    'method', 'granted_at', 'ip_address', 'user_agent', 'recorded_by', 'revoked_at', 'revocation_reason',
])]
class DebtConsent extends Model
{
    /** @use HasFactory<DebtConsentFactory> */
    use HasFactory, HasPublicId;

    public const METHOD_WEB_CHECKBOX = 'web_checkbox';

    public const METHOD_RECORDED_BY_STAFF = 'recorded_by_staff';

    protected static function newFactory(): DebtConsentFactory
    {
        return DebtConsentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'purpose' => ConsentPurpose::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DebtApplication::class, 'debt_application_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lenderProduct(): BelongsTo
    {
        return $this->belongsTo(LenderProduct::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
