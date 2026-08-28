<?php

namespace App\Modules\Eligibility\Models;

use App\Modules\Eligibility\Enums\RulePriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['eligibility_result_id', 'eligibility_rule_id', 'label', 'priority', 'passed', 'customer_message'])]
class EligibilityResultReason extends Model
{
    protected function casts(): array
    {
        return [
            'priority' => RulePriority::class,
            'passed' => 'boolean',
        ];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(EligibilityResult::class, 'eligibility_result_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(EligibilityRule::class, 'eligibility_rule_id');
    }
}
