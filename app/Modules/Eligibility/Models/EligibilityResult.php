<?php

namespace App\Modules\Eligibility\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\LenderProduct;
use App\Modules\Eligibility\Enums\EligibilityStatus;
use App\Modules\Journey\Models\JourneySession;
use Database\Factories\EligibilityResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['journey_session_id', 'lender_product_id', 'eligibility_rule_set_id', 'status', 'foir', 'evaluated_at'])]
class EligibilityResult extends Model
{
    /** @use HasFactory<EligibilityResultFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): EligibilityResultFactory
    {
        return EligibilityResultFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => EligibilityStatus::class,
            'foir' => 'decimal:2',
            'evaluated_at' => 'datetime',
        ];
    }

    public function journeySession(): BelongsTo
    {
        return $this->belongsTo(JourneySession::class);
    }

    public function lenderProduct(): BelongsTo
    {
        return $this->belongsTo(LenderProduct::class);
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(EligibilityRuleSet::class, 'eligibility_rule_set_id');
    }

    public function reasons(): HasMany
    {
        return $this->hasMany(EligibilityResultReason::class);
    }
}
