<?php

use App\Enums\LenderStatus;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Modules\CreditBureau\Contracts\CreditBureauProvider;
use App\Modules\CreditBureau\DataTransferObjects\CreditCheckResult;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
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
use App\Modules\Journey\Models\JourneyResponse;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;
use Illuminate\Testing\TestResponse;

function bindFixedScoreCreditBureauProvider(int $score): void
{
    app()->bind(CreditBureauProvider::class, fn () => new class($score) implements CreditBureauProvider
    {
        public function __construct(private readonly int $score) {}

        public function name(): string
        {
            return 'stub';
        }

        public function check(CreditConsent $consent): CreditCheckResult
        {
            return new CreditCheckResult(status: CreditCheckStatus::Completed, score: $this->score);
        }
    });
}

function completeCreditScoreJourney(int $minCreditScore): TestResponse
{
    $product = LoanProduct::factory()->published()->create();
    $lender = Lender::factory()->create(['status' => LenderStatus::Active, 'name' => 'Credit Score Test Bank']);
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
        'label' => 'Minimum credit score',
        'priority' => RulePriority::Mandatory,
        'logic' => RuleLogic::And,
        'customer_message' => 'Your credit score meets the requirement.',
    ]);
    EligibilityRuleCondition::factory()->create([
        'eligibility_rule_id' => $rule->id,
        'attribute' => 'credit_score',
        'operator' => RuleOperator::GreaterThanOrEqual,
        'value' => $minCreditScore,
    ]);

    $definition = JourneyDefinition::create(['loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active]);
    $step = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'consent', 'title' => 'Consent', 'order' => 1]);
    JourneyStepField::create(['journey_step_id' => $step->id, 'key' => 'pan_number', 'label' => 'PAN', 'type' => FieldType::Text, 'validation_rules' => ['required'], 'order' => 1]);
    JourneyStepField::create(['journey_step_id' => $step->id, 'key' => 'credit_check_consent', 'label' => 'I consent', 'type' => FieldType::Checkbox, 'validation_rules' => ['accepted'], 'order' => 2]);

    test()->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    test()->post(route('journey.update', $session), [
        'pan_number' => 'ABCDE1234F',
        'credit_check_consent' => '1',
    ]);

    return test()->get(route('journey.show', $session));
}

it('persists the checked score as a journey response and passes a Mandatory credit_score rule when high enough', function () {
    bindFixedScoreCreditBureauProvider(780);

    $response = completeCreditScoreJourney(minCreditScore: 700);

    expect(JourneyResponse::query()->where('field_key', 'credit_score')->value('value'))->toBe(780);
    $response->assertOk();
    $response->assertSee('Credit Score Test Bank');
    $response->assertSee('Continue with Credit Score Test Bank');
});

it('fails the Mandatory credit_score rule when the checked score is too low', function () {
    bindFixedScoreCreditBureauProvider(600);

    $response = completeCreditScoreJourney(minCreditScore: 700);

    expect(JourneyResponse::query()->where('field_key', 'credit_score')->value('value'))->toBe(600);
    $response->assertOk();
    $response->assertDontSee('Continue with Credit Score Test Bank');
});
