<?php

use App\Enums\LenderStatus;
use App\Enums\LoanCategory;
use App\Models\CalculatorPage;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Support\Calculators\FlexiHybridTenure;
use App\Support\Localization\SiteLocale;
use Livewire\Livewire;

it('renders the EMI calculator and its schedule dates in Hindi, and keeps Hindi after a value update', function () {
    $this->travelTo('2026-10-14 10:00:00');
    seedCalculatorProduct(LoanCategory::PersonalLoan);
    app()->setLocale('hi');

    $component = Livewire::test('emi-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSee('लोन राशि')
        ->assertSee('मासिक ईएमआई')
        ->assertSee('ब्याज दर (प्रति वर्ष)')
        ->assertSee('अक्टू. 2026')
        ->assertDontSee('Monthly EMI')
        ->assertDontSee('Oct 2026');

    $component->set('principal', 999_999_999)
        ->assertSee('मासिक ईएमआई')
        ->assertDontSee('Total interest');

    expect($component->errors()->first('principal'))->toStartWith('लोन राशि को ');
});

it('renders the investment and prepayment calculators in Hindi', function (string $component, array $parameters, string $hindi, string $english) {
    seedCalculatorProduct(LoanCategory::HomeLoan);
    app()->setLocale('hi');

    Livewire::test($component, $parameters)
        ->assertSee($hindi)
        ->assertDontSee($english);
})->with([
    'fixed deposit' => ['fixed-deposit-calculator', [], 'मैच्योरिटी राशि', 'Maturity value'],
    'monthly SIP' => ['sip-calculator', ['frequency' => 'monthly'], 'हर महीने एक जैसा निवेश', 'Indicative only'],
    'daily SIP' => ['sip-calculator', ['frequency' => 'daily'], 'दैनिक निवेश', 'daily investment'],
    'GST' => ['gst-calculator', [], 'जीएसटी जोड़ें', 'Add GST'],
    'prepayment' => ['loan-prepayment-calculator', ['category' => LoanCategory::HomeLoan->value], 'ब्याज की बचत', 'Interest saved'],
]);

it('spells out the prepayment tenure in Hindi years and months', function () {
    seedCalculatorProduct(LoanCategory::HomeLoan);
    app()->setLocale('hi');

    $component = Livewire::test('loan-prepayment-calculator', ['category' => LoanCategory::HomeLoan->value]);

    expect($component->instance()->formatYearsAndMonths(14))->toBe('1 साल 2 महीने')
        ->and($component->instance()->formatYearsAndMonths(1))->toBe('1 महीना');
});

it('renders the Flexi Hybrid calculator and its repayment structures in Hindi', function () {
    $product = seedCalculatorProduct(LoanCategory::FlexiHybridTermLoan);
    $offer = LenderProduct::factory()->create([
        'lender_id' => Lender::factory()->create(['name' => 'Bajaj Finance', 'slug' => 'bajaj-finance'])->id,
        'loan_product_id' => $product->id,
        'min_tenure_months' => 96,
        'max_tenure_months' => 108,
        'initial_tenure_months' => 24,
    ]);
    publishRate($offer, 11.0);
    app()->setLocale('hi');

    Livewire::test('flexi-hybrid-calculator')
        ->assertSee('सामान्य अनुमान')
        ->assertSee('2 + 6 साल')
        ->assertSee('शुरुआती ईएमआई')
        ->assertDontSee('Generic estimate')
        ->assertDontSee('Initial EMI')
        ->call('selectLender', null)
        ->assertSee('सामान्य अनुमान Bajaj Finance की शुरुआती + बाद की अवधि वाली नीति पर आधारित है।');

    expect(FlexiHybridTenure::label(['total' => 96, 'initial' => 24, 'subsequent' => 72]))->toBe('2 + 6 साल');
});

it('shows the quick eligibility form and its validation errors in Hindi', function () {
    seedCalculatorProduct(LoanCategory::PersonalLoan);
    app()->setLocale('hi');

    $component = Livewire::test('loan-eligibility-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->assertSee('रोज़गार का प्रकार')
        ->assertSee('क्रेडिट रिपोर्ट उपयोग की शर्तें')
        ->assertSeeHtml(route('credit-report-terms'))
        ->assertDontSee('Employment type')
        ->set('age', 10)
        ->call('evaluate');

    expect($component->errors()->first('age'))->toBe('उम्र का मान कम से कम 18 होना चाहिए।')
        ->and($component->errors()->first('monthlyIncome'))->toBe('मासिक आय भरना ज़रूरी है।')
        ->and($component->errors()->first('creditConsent'))->toBe('क्रेडिट रिपोर्ट सहमति स्वीकार करना ज़रूरी है।');
});

it('keeps the quick eligibility validation errors in Laravel\'s own English wording', function () {
    seedCalculatorProduct(LoanCategory::PersonalLoan);

    $component = Livewire::test('loan-eligibility-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('age', 10)
        ->set('existingEmiAmount', -1)
        ->call('evaluate');

    expect($component->errors()->first('age'))->toBe('The age field must be at least 18.')
        ->and($component->errors()->first('existingEmiAmount'))->toBe('The existing emi amount field must be at least 0.')
        ->and($component->errors()->first('loanAmountRequested'))->toBe('The loan amount requested field is required.')
        ->and($component->errors()->first('creditConsent'))->toBe('The credit consent field must be accepted.');
});

it('keeps the expert message a Hindi visitor sends to the contact form in English', function () {
    $product = seedCalculatorProduct(LoanCategory::PersonalLoan, ['name' => 'Personal Loan', 'name_hi' => 'पर्सनल लोन']);
    LenderProduct::factory()->create([
        'lender_id' => Lender::factory()->create(['status' => LenderStatus::Active])->id,
        'loan_product_id' => $product->id,
        'status' => LenderStatus::Active,
    ]);
    app()->setLocale('hi');

    Livewire::test('loan-eligibility-calculator', ['category' => LoanCategory::PersonalLoan->value])
        ->set('age', 30)
        ->set('city', 'Mumbai')
        ->set('monthlyIncome', 60000)
        ->set('loanAmountRequested', 300000)
        ->set('preferredTenureMonths', 36)
        ->set('creditConsent', true)
        ->call('evaluate')
        ->assertSee('FynnEdge विशेषज्ञ से बात करें')
        ->assertSeeHtml('I%20checked%20my%20eligibility%20for%20a%20Personal%20Loan')
        ->assertDontSeeHtml(rawurlencode('पर्सनल लोन'));
});

it('renders the calculator pages\' breadcrumbs and default About heading in Hindi', function () {
    CalculatorPage::factory()->create(['calculator_key' => 'gst', 'title' => null, 'body' => '<p>GST basics.</p>']);

    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
        ->get(route('calculators.gst'))
        ->assertOk()
        ->assertSee('जीएसटी कैलकुलेटर के बारे में')
        ->assertSee('जीएसटी हटाएं')
        ->assertSee('<span class="text-ink" aria-current="page">जीएसटी कैलकुलेटर</span>', false)
        ->assertDontSee('About the GST Calculator')
        ->assertDontSee('Remove GST');
});
