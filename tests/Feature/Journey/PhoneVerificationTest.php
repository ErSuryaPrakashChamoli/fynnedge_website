<?php

use App\Models\LoanProduct;
use App\Modules\CreditScore\Models\MobileOtpChallenge;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;
use Illuminate\Support\Facades\Hash;

function journeyWithPhoneStep(): LoanProduct
{
    $product = LoanProduct::factory()->published()->create();
    $definition = JourneyDefinition::create(['loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active]);
    $step = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'basic', 'title' => 'Basic', 'order' => 1]);

    JourneyStepField::create([
        'journey_step_id' => $step->id, 'key' => 'phone', 'label' => 'Mobile number',
        'type' => FieldType::Tel, 'validation_rules' => ['required', 'regex:/^[6-9]\d{9}$/'], 'order' => 1,
    ]);
    JourneyStepField::create([
        'journey_step_id' => $step->id, 'key' => 'city', 'label' => 'City',
        'type' => FieldType::Text, 'validation_rules' => ['required', 'string'], 'order' => 2,
    ]);

    return $product;
}

it('sends an otp for a valid mobile number', function () {
    $product = journeyWithPhoneStep();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $response = $this->postJson(route('journey.phone.send-otp', $session), ['phone' => '9998887777']);

    $response->assertCreated();
    $response->assertJsonStructure(['otp_challenge_id', 'demo_otp_code']);
    expect(MobileOtpChallenge::query()->where('mobile_number', '9998887777')->exists())->toBeTrue();
});

it('rejects an otp request for a malformed mobile number', function () {
    $product = journeyWithPhoneStep();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $response = $this->postJson(route('journey.phone.send-otp', $session), ['phone' => '12345']);

    $response->assertUnprocessable();
    expect(MobileOtpChallenge::query()->count())->toBe(0);
});

it('verifies the otp and marks the session phone as verified', function () {
    $product = journeyWithPhoneStep();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $challenge = MobileOtpChallenge::factory()->create([
        'mobile_number' => '9998887777',
        'otp_hash' => Hash::make('654321'),
    ]);

    $response = $this->postJson(route('journey.phone.verify-otp', $session), [
        'otp_challenge_id' => $challenge->public_id,
        'otp_code' => '654321',
    ]);

    $response->assertOk()->assertJson(['verified' => true]);
    $session->refresh();
    expect($session->phone_number)->toBe('9998887777');
    expect($session->phone_verified_at)->not->toBeNull();
});

it('rejects an incorrect otp code', function () {
    $product = journeyWithPhoneStep();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $challenge = MobileOtpChallenge::factory()->create([
        'mobile_number' => '9998887777',
        'otp_hash' => Hash::make('654321'),
    ]);

    $response = $this->postJson(route('journey.phone.verify-otp', $session), [
        'otp_challenge_id' => $challenge->public_id,
        'otp_code' => '000000',
    ]);

    $response->assertUnprocessable();
    $session->refresh();
    expect($session->phone_verified_at)->toBeNull();
});

it('refuses to submit a step with an unverified phone number', function () {
    $product = journeyWithPhoneStep();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $response = $this->post(route('journey.update', $session), ['phone' => '9998887777', 'city' => 'Pune']);

    $response->assertSessionHasErrors('phone');
    expect($session->fresh()->currentStep?->key)->toBe('basic');
});

it('refuses to submit a step when the verified number does not match the submitted one', function () {
    $product = journeyWithPhoneStep();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();
    $session->update(['phone_number' => '9998887777', 'phone_verified_at' => now()]);

    $response = $this->post(route('journey.update', $session), ['phone' => '9887766554', 'city' => 'Pune']);

    $response->assertSessionHasErrors('phone');
});

it('lets a verified phone number through to the next step', function () {
    $product = journeyWithPhoneStep();
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();
    $session->update(['phone_number' => '9998887777', 'phone_verified_at' => now()]);

    $response = $this->post(route('journey.update', $session), ['phone' => '9998887777', 'city' => 'Pune']);

    $response->assertSessionDoesntHaveErrors();
});
