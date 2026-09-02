<?php

use App\Enums\LoanCategory;
use App\Models\LoanProduct;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;
use Database\Seeders\CalculatorLoanProductSeeder;
use Database\Seeders\JourneySeeder;

function journeyWithPlatformConsentOnFirstStep(): LoanProduct
{
    $product = LoanProduct::factory()->published()->create();
    $definition = JourneyDefinition::create(['loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active]);

    $basic = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'basic', 'title' => 'Basic details', 'order' => 1]);
    JourneyStepField::create(['journey_step_id' => $basic->id, 'key' => 'city', 'label' => 'City', 'type' => FieldType::Text, 'validation_rules' => ['required'], 'order' => 1]);
    JourneyStepField::create(['journey_step_id' => $basic->id, 'key' => 'platform_consent', 'label' => 'By submitting this form, you have read and agree to the Credit Report Terms of Use, Terms of Use & Privacy Policy.', 'type' => FieldType::Checkbox, 'validation_rules' => ['accepted'], 'order' => 2]);

    $next = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'income', 'title' => 'Income', 'order' => 2]);
    JourneyStepField::create(['journey_step_id' => $next->id, 'key' => 'monthly_income', 'label' => 'Monthly income', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric'], 'order' => 1]);

    return $product;
}

it('renders the platform consent checkbox unchecked, with real links to the compliance pages, on the very first step', function () {
    $product = journeyWithPlatformConsentOnFirstStep();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $response = $this->get(route('journey.show', $session));

    $response->assertOk();
    $response->assertSee('By submitting this form, you have read and agree to the', false);
    $response->assertSee(route('credit-report-terms'), false);
    $response->assertSee(route('terms'), false);
    $response->assertSee(route('privacy-policy'), false);
    $response->assertDontSee('checked', false);
});

it('blocks leaving the first step until platform consent is accepted', function () {
    $product = journeyWithPlatformConsentOnFirstStep();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $response = $this->post(route('journey.update', $session), ['city' => 'Pune']);

    $response->assertSessionHasErrors(['platform_consent']);
    $session->refresh();
    expect($session->currentStep->key)->toBe('basic');
});

it('advances past the first step once platform consent is accepted', function () {
    $product = journeyWithPlatformConsentOnFirstStep();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $response = $this->post(route('journey.update', $session), ['city' => 'Pune', 'platform_consent' => '1']);

    $response->assertSessionDoesntHaveErrors();
    $session->refresh();
    expect($session->currentStep->key)->toBe('income');
});

it('seeds the platform consent field onto the basic-details step for every real loan product', function () {
    LoanProduct::factory()->published()->create(['slug' => 'personal-loan', 'category' => LoanCategory::PersonalLoan]);
    $this->seed(CalculatorLoanProductSeeder::class);

    $this->seed(JourneySeeder::class);

    $basicStepsMissingConsent = JourneyStep::query()
        ->where('key', 'basic-details')
        ->whereDoesntHave('fields', fn ($query) => $query->where('key', 'platform_consent'))
        ->count();

    expect(JourneyStep::query()->where('key', 'basic-details')->count())->toBeGreaterThan(0);
    expect($basicStepsMissingConsent)->toBe(0);
});
