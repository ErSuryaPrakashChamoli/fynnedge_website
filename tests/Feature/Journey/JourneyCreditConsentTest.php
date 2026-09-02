<?php

use App\Models\LoanProduct;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Models\CreditCheck;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneyResponse;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;

function journeyWithConsentStep(): LoanProduct
{
    $product = LoanProduct::factory()->published()->create();
    $definition = JourneyDefinition::create(['loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active]);

    $basic = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'basic', 'title' => 'Basic', 'order' => 1]);
    JourneyStepField::create(['journey_step_id' => $basic->id, 'key' => 'city', 'label' => 'City', 'type' => FieldType::Text, 'validation_rules' => ['required'], 'order' => 1]);

    $consent = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'consent', 'title' => 'Consent', 'order' => 2]);
    JourneyStepField::create(['journey_step_id' => $consent->id, 'key' => 'credit_check_consent', 'label' => 'I consent', 'type' => FieldType::Checkbox, 'validation_rules' => ['accepted'], 'order' => 1]);

    return $product;
}

it('records credit consent when the consent step is submitted', function () {
    $product = journeyWithConsentStep();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $this->post(route('journey.update', $session), ['city' => 'Pune']);
    $this->post(route('journey.update', $session), ['credit_check_consent' => '1']);

    $consent = CreditConsent::query()->where('journey_session_id', $session->id)->first();
    expect($consent)->not->toBeNull();
    expect($consent->ip_address)->not->toBeNull();
});

it('attempts a real credit check on consent, but the default Null provider fails it honestly rather than fabricate a score', function () {
    $product = journeyWithConsentStep();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $this->post(route('journey.update', $session), ['city' => 'Pune']);
    $this->post(route('journey.update', $session), ['credit_check_consent' => '1']);

    $check = CreditCheck::query()->first();
    expect(CreditCheck::query()->count())->toBe(1);
    expect($check->status)->toBe(CreditCheckStatus::Failed);
    expect($check->score)->toBeNull();

    expect(JourneyResponse::query()->where('field_key', 'credit_score')->exists())->toBeFalse();
});

it('renders the consent checkbox with real links to the compliance pages', function () {
    $product = journeyWithConsentStep();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();
    $this->post(route('journey.update', $session), ['city' => 'Pune']);

    $response = $this->get(route('journey.show', $session));

    $response->assertOk();
    $response->assertSee(route('credit-report-terms'), false);
    $response->assertSee(route('terms'), false);
    $response->assertSee(route('privacy-policy'), false);
});

it('does not record consent for steps that never mention it', function () {
    $product = journeyWithConsentStep();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $this->post(route('journey.update', $session), ['city' => 'Pune']);

    expect(CreditConsent::query()->where('journey_session_id', $session->id)->exists())->toBeFalse();
});
