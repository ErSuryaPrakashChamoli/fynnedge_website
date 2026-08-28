<?php

use App\Enums\LenderStatus;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use App\Modules\Eligibility\Enums\RuleLogic;
use App\Modules\Eligibility\Enums\RuleOperator;
use App\Modules\Eligibility\Enums\RulePriority;
use App\Modules\Eligibility\Models\EligibilityRule;
use App\Modules\Eligibility\Models\EligibilityRuleCondition;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;

function buildTwoStepJourney(): LoanProduct
{
    $product = LoanProduct::factory()->published()->create();
    $lender = Lender::factory()->create(['status' => LenderStatus::Active]);
    $lenderProduct = LenderProduct::factory()->create([
        'lender_id' => $lender->id,
        'loan_product_id' => $product->id,
        'status' => LenderStatus::Active,
    ]);
    $ruleSet = EligibilityRuleSet::factory()->create([
        'lender_product_id' => $lenderProduct->id,
        'status' => EligibilityRuleSetStatus::Active,
    ]);
    $rule = EligibilityRule::factory()->create([
        'eligibility_rule_set_id' => $ruleSet->id,
        'priority' => RulePriority::Mandatory,
        'logic' => RuleLogic::And,
    ]);
    EligibilityRuleCondition::factory()->create([
        'eligibility_rule_id' => $rule->id,
        'attribute' => 'monthly_income',
        'operator' => RuleOperator::GreaterThanOrEqual,
        'value' => 10000,
    ]);

    $definition = JourneyDefinition::create(['loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active]);
    $step1 = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'income', 'title' => 'Income', 'order' => 1]);
    JourneyStepField::create(['journey_step_id' => $step1->id, 'key' => 'monthly_income', 'label' => 'Income', 'type' => FieldType::Number, 'validation_rules' => ['required'], 'order' => 1]);
    $step2 = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'consent', 'title' => 'Consent', 'order' => 2]);
    JourneyStepField::create(['journey_step_id' => $step2->id, 'key' => 'credit_check_consent', 'label' => 'Consent', 'type' => FieldType::Checkbox, 'validation_rules' => ['accepted'], 'order' => 1]);

    return $product;
}

it('tracks journey_started when a session begins', function () {
    $product = buildTwoStepJourney();

    $this->get("/loans/{$product->slug}/apply");

    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    expect(AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::JourneyStarted)->where('journey_session_id', $session->id)->count())->toBe(1);
});

it('tracks a step-completed event per step and journey/eligibility events only on the final step', function () {
    $product = buildTwoStepJourney();

    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $this->post(route('journey.update', $session), ['monthly_income' => 50000]);

    expect(AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::JourneyStepCompleted)->count())->toBe(1);
    expect(AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::JourneyCompleted)->count())->toBe(0);
    expect(AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::EligibilityEvaluated)->count())->toBe(0);

    $this->post(route('journey.update', $session), ['credit_check_consent' => 1]);

    expect(AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::JourneyStepCompleted)->count())->toBe(2);
    expect(AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::JourneyCompleted)->count())->toBe(1);

    $eligibilityEvent = AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::EligibilityEvaluated)->firstOrFail();
    expect($eligibilityEvent->properties['status'])->toBe('eligible');
    expect($eligibilityEvent->loan_product_id)->toBe($product->id);
});
