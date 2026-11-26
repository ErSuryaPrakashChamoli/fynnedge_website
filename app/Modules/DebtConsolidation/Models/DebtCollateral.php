<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Modules\DebtConsolidation\Enums\CollateralType;
use App\Modules\DebtConsolidation\Enums\VerificationStatus;
use Database\Factories\DebtCollateralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'debt_application_id', 'collateral_type', 'description', 'owner_name', 'ownership', 'estimated_value',
    'existing_charge_amount', 'city', 'pincode', 'verification_status', 'verified_by', 'verified_at', 'notes',
])]
class DebtCollateral extends Model
{
    /** @use HasFactory<DebtCollateralFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected static function newFactory(): DebtCollateralFactory
    {
        return DebtCollateralFactory::new();
    }

    protected function casts(): array
    {
        return [
            'collateral_type' => CollateralType::class,
            'estimated_value' => 'decimal:2',
            'existing_charge_amount' => 'decimal:2',
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['owner_name', 'pincode'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DebtApplication::class, 'debt_application_id');
    }

    /**
     * Value left after loans already secured on the asset.
     */
    public function freeValue(): ?float
    {
        return $this->estimated_value === null ? null : max(0.0, (float) $this->estimated_value - (float) $this->existing_charge_amount);
    }
}
