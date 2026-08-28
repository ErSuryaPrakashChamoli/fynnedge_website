<?php

namespace Database\Seeders;

use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use App\Modules\Eligibility\Enums\RuleLogic;
use App\Modules\Eligibility\Enums\RuleOperator;
use App\Modules\Eligibility\Enums\RulePriority;
use App\Modules\Eligibility\Models\EligibilityRule;
use App\Modules\Eligibility\Models\EligibilityRuleCondition;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use App\Modules\Eligibility\Models\Employer;
use App\Modules\Eligibility\Models\EmployerCategory;
use App\Modules\Eligibility\Models\EmployerRating;
use Illuminate\Database\Seeder;

/**
 * Seeds a demonstration eligibility rule set for the DEMO Lender created in
 * DatabaseSeeder. Thresholds are informed by publicly reported Indian personal
 * loan lending patterns (typical minimum income, FOIR limits, age bands, and a
 * bank-tiered employer category scheme modeled on how banks like Bank of
 * Maharashtra publicly document Category A/B/C employer classification) — they
 * are illustrative starting points for this DEMO lender, not any real bank's
 * actual current criteria. Everything here is editable by an admin at any time
 * through the Eligibility Rules and Lenders resources.
 */
class EligibilitySeeder extends Seeder
{
    public function run(): void
    {
        $personalLoan = LoanProduct::query()->where('slug', 'personal-loan')->first();

        if (! $personalLoan) {
            return;
        }

        $lenderProduct = LenderProduct::query()->where('loan_product_id', $personalLoan->id)->first();

        if (! $lenderProduct) {
            return;
        }

        $lender = $lenderProduct->lender;

        $categoryA = EmployerCategory::query()->updateOrCreate(
            ['lender_id' => $lender->id, 'key' => 'A'],
            ['label' => 'Category A — Government / PSU / Listed MNC', 'order' => 1],
        );
        $categoryB = EmployerCategory::query()->updateOrCreate(
            ['lender_id' => $lender->id, 'key' => 'B'],
            ['label' => 'Category B — Private Limited (rated)', 'order' => 2],
        );
        EmployerCategory::query()->updateOrCreate(
            ['lender_id' => $lender->id, 'key' => 'C'],
            ['label' => 'Category C — Other / SME / Unrated', 'order' => 3],
        );

        $demoEmployer = Employer::query()->firstOrCreate(
            ['name' => 'DEMO Employer Pvt Ltd'],
            ['notes' => 'Seed data for local development and the eligibility tester — not a real company.'],
        );
        EmployerRating::query()->updateOrCreate(
            ['employer_id' => $demoEmployer->id, 'lender_id' => $lender->id],
            ['employer_category_id' => $categoryA->id],
        );

        $ruleSet = EligibilityRuleSet::query()->updateOrCreate(
            ['lender_product_id' => $lenderProduct->id, 'version' => 1],
            [
                'status' => EligibilityRuleSetStatus::Active,
                'notes' => 'DEMO criteria seeded from typical published Indian personal loan lending patterns — not real bank criteria.',
            ],
        );

        $this->rule($ruleSet, 'Age between 21 and 60', RulePriority::Mandatory, RuleLogic::And, [
            ['attribute' => 'age', 'operator' => RuleOperator::Between, 'value' => [21, 60]],
        ], 'You meet the age requirement for this lender.');

        $this->rule($ruleSet, 'Minimum monthly income', RulePriority::Mandatory, RuleLogic::And, [
            ['attribute' => 'total_monthly_income', 'operator' => RuleOperator::GreaterThanOrEqual, 'value' => 25000],
        ], 'Your income meets this lender\'s minimum requirement.');

        $this->rule($ruleSet, 'Minimum employment vintage', RulePriority::Mandatory, RuleLogic::And, [
            ['attribute' => 'employment_vintage_years', 'operator' => RuleOperator::GreaterThanOrEqual, 'value' => 1],
        ], 'Your employment history meets the minimum requirement.');

        $this->rule($ruleSet, 'FOIR within limit', RulePriority::Mandatory, RuleLogic::And, [
            ['attribute' => 'foir', 'operator' => RuleOperator::LessThanOrEqual, 'value' => 50],
        ], 'Your existing obligations are within this lender\'s permitted range.');

        $this->rule($ruleSet, 'Preferred employer category', RulePriority::Preferred, RuleLogic::And, [
            ['attribute' => 'employer_category', 'operator' => RuleOperator::In, 'value' => ['A', 'B']],
        ], 'Your employer is in this lender\'s preferred category.');

        $this->rule($ruleSet, 'Has existing obligations', RulePriority::Warning, RuleLogic::And, [
            ['attribute' => 'has_existing_emis', 'operator' => RuleOperator::Equals, 'value' => 'yes'],
        ], 'You have existing loan EMIs — this may affect the final offer.');
    }

    /**
     * @param  array<int, array{attribute: string, operator: RuleOperator, value: mixed}>  $conditions
     */
    private function rule(
        EligibilityRuleSet $ruleSet,
        string $label,
        RulePriority $priority,
        RuleLogic $logic,
        array $conditions,
        string $customerMessage,
    ): void {
        static $order = 0;
        $order++;

        $rule = EligibilityRule::query()->updateOrCreate(
            ['eligibility_rule_set_id' => $ruleSet->id, 'label' => $label],
            [
                'priority' => $priority,
                'logic' => $logic,
                'customer_message' => $customerMessage,
                'order' => $order,
            ],
        );

        foreach ($conditions as $index => $condition) {
            EligibilityRuleCondition::query()->updateOrCreate(
                ['eligibility_rule_id' => $rule->id, 'attribute' => $condition['attribute']],
                [
                    'operator' => $condition['operator'],
                    'value' => $condition['value'],
                    'order' => $index + 1,
                ],
            );
        }
    }
}
