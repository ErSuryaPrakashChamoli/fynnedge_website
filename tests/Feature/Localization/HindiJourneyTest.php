<?php

use App\Enums\LenderStatus;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Modules\Applications\Enums\ApplicationStatus;
use App\Modules\Applications\Models\Application;
use App\Modules\CreditBureau\Actions\RecordCreditConsent;
use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use App\Modules\Eligibility\Enums\RuleLogic;
use App\Modules\Eligibility\Enums\RuleOperator;
use App\Modules\Eligibility\Enums\RulePriority;
use App\Modules\Eligibility\Models\EligibilityResultReason;
use App\Modules\Eligibility\Models\EligibilityRule;
use App\Modules\Eligibility\Models\EligibilityRuleCondition;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;
use App\Modules\Newsletter\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Services\NewsletterSettings;
use App\Support\Localization\SiteLocale;
use Livewire\Livewire;

beforeEach(function () {
    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi');
});

/**
 * Starts the one active journey of a published product and returns its session.
 */
function startHindiJourney(LoanProduct $product): JourneySession
{
    test()->get("/loans/{$product->slug}/apply");

    return JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();
}

it('renders a journey step in Hindi while every option still posts its English value', function () {
    $product = LoanProduct::factory()->published()->create();
    $definition = JourneyDefinition::create(['loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active]);
    $step = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'work', 'title' => 'Your work', 'title_hi' => 'आपका काम', 'order' => 1]);
    JourneyStepField::create([
        'journey_step_id' => $step->id,
        'key' => 'employment_type',
        'label' => 'Employment type',
        'label_hi' => 'रोज़गार का प्रकार',
        'type' => FieldType::Select,
        'options' => [['value' => 'salaried', 'label' => 'Salaried'], ['value' => 'self-employed', 'label' => 'Self-employed']],
        'options_hi' => [['value' => 'salaried', 'label' => 'वेतनभोगी'], ['value' => 'स्व-रोज़गार', 'label' => 'स्व-रोज़गार']],
        'validation_rules' => ['required'],
        'order' => 1,
    ]);
    $session = startHindiJourney($product);

    $response = $this->get(route('journey.show', $session));

    $response->assertSee('आपका काम')
        ->assertSee('रोज़गार का प्रकार')
        ->assertSee('चुनें…')
        ->assertSee('आगे बढ़ें')
        ->assertSeeInOrder(['value="salaried"', 'वेतनभोगी', 'value="self-employed"', 'Self-employed'], false)
        ->assertDontSee('value="स्व-रोज़गार"', false)
        ->assertDontSee('Your work');
});

it('stores the English eligibility reason but shows a Hindi visitor the Hindi one', function () {
    $product = LoanProduct::factory()->published()->create();
    $lenderProduct = LenderProduct::factory()->create([
        'lender_id' => Lender::factory()->create(['status' => LenderStatus::Active, 'name' => 'Hindi Test Bank'])->id,
        'loan_product_id' => $product->id,
        'status' => LenderStatus::Active,
    ]);
    $ruleSet = EligibilityRuleSet::factory()->create(['lender_product_id' => $lenderProduct->id, 'status' => EligibilityRuleSetStatus::Active]);
    $rule = EligibilityRule::factory()->create([
        'eligibility_rule_set_id' => $ruleSet->id,
        'label' => 'Minimum income',
        'priority' => RulePriority::Mandatory,
        'logic' => RuleLogic::And,
        'customer_message' => 'Your income meets the requirement',
        'customer_message_hi' => 'आपकी आय ज़रूरत पूरी करती है',
    ]);
    EligibilityRuleCondition::factory()->create([
        'eligibility_rule_id' => $rule->id,
        'attribute' => 'monthly_income',
        'operator' => RuleOperator::GreaterThanOrEqual,
        'value' => 10000,
    ]);
    $definition = JourneyDefinition::create(['loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active]);
    $step = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'income', 'title' => 'Income', 'order' => 1]);
    JourneyStepField::create(['journey_step_id' => $step->id, 'key' => 'monthly_income', 'label' => 'Income', 'type' => FieldType::Number, 'validation_rules' => ['required'], 'order' => 1]);
    $session = startHindiJourney($product);

    $this->post(route('journey.update', $session), ['monthly_income' => 50000]);
    $response = $this->get(route('journey.show', $session));

    expect(EligibilityResultReason::query()->sole()->customer_message)->toBe('Your income meets the requirement');
    $response->assertSee('आपकी आय ज़रूरत पूरी करती है')
        ->assertSee('आप 1 लेंडर के साथ पात्र हो सकते हैं')
        ->assertSee('Hindi Test Bank के साथ आगे बढ़ें')
        ->assertDontSee('Your income meets the requirement')
        ->assertDontSee('Eligibility results are indicative');
});

it('records the credit consent purpose in English for a Hindi visitor', function () {
    app()->setLocale('hi');
    $session = JourneySession::factory()->create();
    $session->loanProduct->update(['name' => 'Personal Loan', 'name_hi' => 'पर्सनल लोन']);

    $consent = app(RecordCreditConsent::class)->handle($session->fresh());

    expect($consent->purpose)->toBe('Personal Loan eligibility check');
});

it('runs the credit score check in Hindi, including its error messages', function () {
    fakeAreachSms();
    app()->setLocale('hi');

    $component = Livewire::test('credit-score-check', ['bureau' => 'cibil'])
        ->assertSee('मुफ़्त CIBIL स्कोर पाएं')
        ->assertDontSee('Get Free CIBIL Score')
        ->call('sendOtp')
        ->assertSee('मोबाइल नंबर भरना ज़रूरी है।')
        ->set('mobileNumber', '9876543210')
        ->call('sendOtp')
        ->set('otpCode', '000000')
        ->call('verifyOtp')
        ->assertSee('यह कोड गलत है या इसकी समय-सीमा खत्म हो गई है। आप नया कोड दोबारा भेज सकते हैं।')
        ->set('otpCode', lastTextedOtp())
        ->call('verifyOtp')
        ->set('fullName', 'Jane Doe')
        ->set('dateOfBirth', '1990-01-01')
        ->set('panNumber', 'NOTAPAN')
        ->call('submitDetails');

    $component->assertSee('सही पैन दर्ज करें (जैसे ABCDE1234F)।')
        ->assertSee('आगे बढ़ने के लिए कृपया सहमति दें।')
        ->assertDontSee('Enter a valid PAN');
});

it('renders the application page and its status in Hindi', function () {
    $application = Application::factory()->create(['status' => ApplicationStatus::LenderSelected]);

    $this->get(route('applications.show', $application))
        ->assertSee('आप कैसे आगे बढ़ना चाहेंगे?')
        ->assertSee('लेंडर चुना गया')
        ->assertSee('दस्तावेज़ खुद अपलोड करें')
        ->assertDontSee('How would you like to continue?');
});

it('renders the newsletter preferences page in Hindi', function () {
    NewsletterSubscriber::factory()->active('unsub-token')->create(['email' => 'reader@example.com']);

    $this->get(route('newsletter.preferences', ['token' => 'unsub-token']))
        ->assertSee('ईमेल प्राथमिकताएं')
        ->assertSee('क्रेडिट और CIBIL')
        ->assertSee('चुनें कि reader@example.com पर कौन-से ईमेल आएं।')
        ->assertDontSee('Save preferences');
});

it('serves the newsletter footer copy in Hindi', function () {
    app()->setLocale('hi');

    expect(NewsletterSettings::footerHeading())->toBe('FynnEdge इनसाइट्स');

    Setting::set('newsletter_footer_heading', 'Money Notes');

    expect(NewsletterSettings::footerHeading())->toBe('Money Notes');

    Setting::set('newsletter_footer_heading_hi', 'पैसों की बातें');

    expect(NewsletterSettings::footerHeading())->toBe('पैसों की बातें');
});
