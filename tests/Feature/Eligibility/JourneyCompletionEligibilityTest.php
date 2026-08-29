<?php

use App\Enums\LenderStatus;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use App\Modules\Eligibility\Enums\RuleLogic;
use App\Modules\Eligibility\Enums\RuleOperator;
use App\Modules\Eligibility\Enums\RulePriority;
use App\Modules\Eligibility\Models\EligibilityResult;
use App\Modules\Eligibility\Models\EligibilityRule;
use App\Modules\Eligibility\Models\EligibilityRuleCondition;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;
use Illuminate\Testing\TestResponse;

function completeJourneyWithEmiAnswer(string $hasExistingEmis): TestResponse
{
    $product = LoanProduct::factory()->published()->create();
    $lender = Lender::factory()->create(['status' => LenderStatus::Active, 'name' => 'Warning Test Bank']);
    $lenderProduct = LenderProduct::factory()->create([
        'lender_id' => $lender->id,
        'loan_product_id' => $product->id,
        'status' => LenderStatus::Active,
    ]);
    $ruleSet = EligibilityRuleSet::factory()->create([
        'lender_product_id' => $lenderProduct->id,
        'status' => EligibilityRuleSetStatus::Active,
    ]);

    $mandatory = EligibilityRule::factory()->create([
        'eligibility_rule_set_id' => $ruleSet->id,
        'label' => 'Minimum income',
        'priority' => RulePriority::Mandatory,
        'logic' => RuleLogic::And,
        'customer_message' => 'Your income meets the requirement',
    ]);
    EligibilityRuleCondition::factory()->create([
        'eligibility_rule_id' => $mandatory->id,
        'attribute' => 'monthly_income',
        'operator' => RuleOperator::GreaterThanOrEqual,
        'value' => 10000,
    ]);

    $warning = EligibilityRule::factory()->create([
        'eligibility_rule_set_id' => $ruleSet->id,
        'label' => 'Has existing obligations',
        'priority' => RulePriority::Warning,
        'logic' => RuleLogic::And,
        'customer_message' => 'You have existing loan EMIs — this may affect the final offer.',
    ]);
    EligibilityRuleCondition::factory()->create([
        'eligibility_rule_id' => $warning->id,
        'attribute' => 'has_existing_emis',
        'operator' => RuleOperator::Equals,
        'value' => 'yes',
    ]);

    $definition = JourneyDefinition::create(['loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active]);
    $step = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'basic', 'title' => 'Basic', 'order' => 1]);
    JourneyStepField::create(['journey_step_id' => $step->id, 'key' => 'monthly_income', 'label' => 'Income', 'type' => FieldType::Number, 'validation_rules' => ['required'], 'order' => 1]);
    JourneyStepField::create(['journey_step_id' => $step->id, 'key' => 'has_existing_emis', 'label' => 'Has EMIs', 'type' => FieldType::Text, 'validation_rules' => ['required'], 'order' => 2]);

    test()->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    test()->post(route('journey.update', $session), [
        'monthly_income' => 50000,
        'has_existing_emis' => $hasExistingEmis,
    ]);

    return test()->get(route('journey.show', $session));
}

it('shows the warning reason when the flagged condition is present', function () {
    $response = completeJourneyWithEmiAnswer('yes');

    $response->assertOk();
    $response->assertSee('existing loan EMIs');
});

it('hides the warning reason when the flagged condition is absent', function () {
    $response = completeJourneyWithEmiAnswer('no');

    $response->assertOk();
    $response->assertDontSee('existing loan EMIs');
    $response->assertSee('Warning Test Bank');
    $response->assertSee('WT');
});

it('offers a continue-with-lender action only for eligible results', function () {
    $response = completeJourneyWithEmiAnswer('no');

    $response->assertOk();
    $response->assertSee('Continue with Warning Test Bank');
    $response->assertSee(route('applications.select', EligibilityResult::query()->latest('id')->firstOrFail()), false);
});
