<?php

namespace App\Modules\Eligibility\Actions;

use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use App\Modules\Eligibility\Enums\RulePriority;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use Illuminate\Support\Facades\DB;

class PublishEligibilityRuleSet
{
    /**
     * Activates a rule set after validating it won't accidentally leave every
     * applicant eligible by default, and archives whichever version of this
     * lender product's rules was previously active.
     *
     * @return array<int, string> validation errors — empty means it was published
     */
    public function handle(EligibilityRuleSet $ruleSet): array
    {
        $errors = $this->validate($ruleSet);

        if ($errors !== []) {
            return $errors;
        }

        DB::transaction(function () use ($ruleSet): void {
            EligibilityRuleSet::query()
                ->where('lender_product_id', $ruleSet->lender_product_id)
                ->where('id', '!=', $ruleSet->id)
                ->where('status', EligibilityRuleSetStatus::Active)
                ->update(['status' => EligibilityRuleSetStatus::Archived]);

            $ruleSet->update([
                'status' => EligibilityRuleSetStatus::Active,
                'effective_from' => $ruleSet->effective_from ?? now()->toDateString(),
            ]);
        });

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function validate(EligibilityRuleSet $ruleSet): array
    {
        $errors = [];
        $rules = $ruleSet->rules()->with('conditions')->get();

        if ($rules->isEmpty()) {
            $errors[] = 'This rule set has no rules yet — add at least one before publishing.';
        }

        foreach ($rules as $rule) {
            if ($rule->conditions->isEmpty()) {
                $errors[] = "Rule \"{$rule->label}\" has no conditions — remove it or add at least one.";
            }
        }

        if ($rules->isNotEmpty() && ! $rules->contains(fn ($rule) => $rule->priority === RulePriority::Mandatory)) {
            $errors[] = 'At least one mandatory rule is required, otherwise every applicant would be eligible by default.';
        }

        if ($ruleSet->effective_from && $ruleSet->effective_until && $ruleSet->effective_from->gt($ruleSet->effective_until)) {
            $errors[] = 'The effective-from date must be before the effective-until date.';
        }

        return $errors;
    }
}
