<?php

namespace App\Modules\Eligibility\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTranslations;
use App\Modules\Eligibility\Enums\RuleLogic;
use App\Modules\Eligibility\Enums\RulePriority;
use Database\Factories\EligibilityRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['eligibility_rule_set_id', 'label', 'priority', 'logic', 'customer_message', 'order', 'label_hi', 'customer_message_hi'])]
class EligibilityRule extends Model
{
    /** @use HasFactory<EligibilityRuleFactory> */
    use Auditable, HasFactory, HasPublicId, HasTranslations;

    protected static function newFactory(): EligibilityRuleFactory
    {
        return EligibilityRuleFactory::new();
    }

    protected function casts(): array
    {
        return [
            'priority' => RulePriority::class,
            'logic' => RuleLogic::class,
        ];
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(EligibilityRuleSet::class, 'eligibility_rule_set_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(EligibilityRuleCondition::class)->orderBy('order');
    }

    /**
     * @return list<string>
     */
    public function translatableAttributes(): array
    {
        return [
            'label',
            'customer_message',
        ];
    }
}
