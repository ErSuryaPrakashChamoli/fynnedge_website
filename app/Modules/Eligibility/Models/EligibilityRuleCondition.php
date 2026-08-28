<?php

namespace App\Modules\Eligibility\Models;

use App\Modules\Eligibility\Enums\RuleOperator;
use Database\Factories\EligibilityRuleConditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['eligibility_rule_id', 'attribute', 'operator', 'value', 'order'])]
class EligibilityRuleCondition extends Model
{
    /** @use HasFactory<EligibilityRuleConditionFactory> */
    use HasFactory;

    protected static function newFactory(): EligibilityRuleConditionFactory
    {
        return EligibilityRuleConditionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'operator' => RuleOperator::class,
            'value' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(EligibilityRule::class, 'eligibility_rule_id');
    }
}
