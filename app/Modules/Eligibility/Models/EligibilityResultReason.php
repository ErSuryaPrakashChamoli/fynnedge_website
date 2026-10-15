<?php

namespace App\Modules\Eligibility\Models;

use App\Modules\Eligibility\Enums\RulePriority;
use App\Support\Localization\SiteLocale;
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

    /**
     * The stored message is the English one (admins read it). While the site runs in
     * Hindi, the rule's translated message is shown instead when the rule still exists
     * and has one; otherwise the stored English.
     */
    public function visitorMessage(): ?string
    {
        if (SiteLocale::isTranslating() && filled($this->customer_message) && filled($this->rule?->customer_message)) {
            return $this->rule->customer_message;
        }

        return $this->customer_message;
    }
}
