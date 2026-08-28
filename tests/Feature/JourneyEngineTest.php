<?php

use App\Enums\PublishStatus;
use App\Models\LoanProduct;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Enums\JourneySessionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;

function buildTestJourney(): LoanProduct
{
    $product = LoanProduct::factory()->published()->create(['name' => 'Test Loan', 'slug' => 'test-loan']);
    $definition = JourneyDefinition::create([
        'loan_product_id' => $product->id,
        'version' => 1,
        'status' => JourneyDefinitionStatus::Active,
    ]);

    $basic = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'basic', 'title' => 'Basic details', 'order' => 1]);
    JourneyStepField::create([
        'journey_step_id' => $basic->id, 'key' => 'employment_type', 'label' => 'Employment type',
        'type' => FieldType::Select, 'options' => [['value' => 'salaried', 'label' => 'Salaried'], ['value' => 'self', 'label' => 'Self-employed']],
        'validation_rules' => ['required'], 'order' => 1,
    ]);
    JourneyStepField::create([
        'journey_step_id' => $basic->id, 'key' => 'company_name', 'label' => 'Company name',
        'type' => FieldType::Text, 'validation_rules' => ['required', 'string'],
        'conditional_on' => ['field' => 'employment_type', 'operator' => '=', 'value' => 'salaried'], 'order' => 2,
    ]);

    $income = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'income', 'title' => 'Income', 'order' => 2]);
    JourneyStepField::create([
        'journey_step_id' => $income->id, 'key' => 'monthly_income', 'label' => 'Monthly income',
        'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric'], 'order' => 1,
    ]);

    $consent = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'consent', 'title' => 'Consent', 'order' => 3]);
    JourneyStepField::create([
        'journey_step_id' => $consent->id, 'key' => 'agree', 'label' => 'I agree',
        'type' => FieldType::Checkbox, 'validation_rules' => ['accepted'], 'order' => 1,
    ]);

    return $product;
}

it('lists published products on the eligibility picker', function () {
    buildTestJourney();
    LoanProduct::factory()->create(['status' => PublishStatus::Draft, 'name' => 'Draft Product']);

    $response = $this->get('/eligibility');

    $response->assertOk();
    $response->assertSee('Test Loan');
    $response->assertDontSee('Draft Product');
});

it('starts a journey session and redirects to the first step', function () {
    $product = buildTestJourney();

    $response = $this->get("/loans/{$product->slug}/apply");

    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();
    $response->assertRedirect(route('journey.show', $session));
    expect($session->currentStep->key)->toBe('basic');
    expect($session->status)->toBe(JourneySessionStatus::InProgress);
});

it('shows a friendly message when a product has no active journey', function () {
    $product = LoanProduct::factory()->published()->create();

    $response = $this->from('/loans/'.$product->slug)->get("/loans/{$product->slug}/apply");

    $response->assertRedirect('/loans/'.$product->slug);
    $response->assertSessionHas('status');
});

it('renders the current step with its fields', function () {
    $product = buildTestJourney();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $response = $this->get(route('journey.show', $session));

    $response->assertOk();
    $response->assertSee('Employment type');
    $response->assertSee('Company name');
});

it('requires a same-step conditional field when its trigger is answered in this submission', function () {
    $product = buildTestJourney();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $response = $this->post(route('journey.update', $session), ['employment_type' => 'salaried']);

    $response->assertSessionHasErrors(['company_name']);
    $session->refresh();
    expect($session->currentStep->key)->toBe('basic');
});

it('does not require the conditional field when its trigger condition is not met', function () {
    $product = buildTestJourney();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $response = $this->post(route('journey.update', $session), ['employment_type' => 'self']);

    $response->assertSessionDoesntHaveErrors();
    $session->refresh();
    expect($session->currentStep->key)->toBe('income');
});

it('advances through every step and completes the journey', function () {
    $product = buildTestJourney();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $this->post(route('journey.update', $session), ['employment_type' => 'salaried', 'company_name' => 'Acme Corp']);
    $session->refresh();
    expect($session->currentStep->key)->toBe('income');

    $this->post(route('journey.update', $session), ['monthly_income' => 75000]);
    $session->refresh();
    expect($session->currentStep->key)->toBe('consent');

    $response = $this->post(route('journey.update', $session), ['agree' => '1']);
    $session->refresh();

    $response->assertRedirect(route('journey.show', $session));
    expect($session->status)->toBe(JourneySessionStatus::Completed);
    expect($session->completed_at)->not->toBeNull();

    $this->get(route('journey.show', $session))->assertOk()->assertSee('got your details');

    expect($session->responsesByKey())->toMatchArray([
        'employment_type' => 'salaried',
        'company_name' => 'Acme Corp',
        'monthly_income' => 75000,
    ]);
});

it('rejects consent without acceptance', function () {
    $product = buildTestJourney();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $this->post(route('journey.update', $session), ['employment_type' => 'self']);
    $this->post(route('journey.update', $session), ['monthly_income' => 50000]);

    $response = $this->post(route('journey.update', $session), []);

    $response->assertSessionHasErrors(['agree']);
    $session->refresh();
    expect($session->status)->toBe(JourneySessionStatus::InProgress);
});

it('goes back to the previous step without losing already-saved answers', function () {
    $product = buildTestJourney();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $this->post(route('journey.update', $session), ['employment_type' => 'self']);
    $session->refresh();
    expect($session->currentStep->key)->toBe('income');

    $this->post(route('journey.back', $session));
    $session->refresh();

    expect($session->currentStep->key)->toBe('basic');
    expect($session->responsesByKey())->toHaveKey('employment_type', 'self');
});

it('404s when submitting to an already-completed session', function () {
    $product = buildTestJourney();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();
    $session->update(['status' => JourneySessionStatus::Completed, 'completed_at' => now()]);

    $this->post(route('journey.update', $session), ['whatever' => 'value'])->assertNotFound();
});
