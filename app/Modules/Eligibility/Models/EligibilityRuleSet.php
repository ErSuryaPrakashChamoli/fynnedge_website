<?php

namespace App\Modules\Eligibility\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\LenderProduct;
use App\Models\User;
use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use Database\Factories\EligibilityRuleSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['lender_product_id', 'version', 'status', 'effective_from', 'effective_until', 'created_by', 'notes'])]
class EligibilityRuleSet extends Model
{
    /** @use HasFactory<EligibilityRuleSetFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected static function newFactory(): EligibilityRuleSetFactory
    {
        return EligibilityRuleSetFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => EligibilityRuleSetStatus::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function lenderProduct(): BelongsTo
    {
        return $this->belongsTo(LenderProduct::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(EligibilityRule::class)->orderBy('order');
    }
}
