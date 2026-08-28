<?php

use App\Models\LoanProduct;
use App\Modules\Customers\Services\ProfileNormalizer;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneyResponse;
use App\Modules\Journey\Models\JourneySession;

function sessionWithResponses(array $responses): JourneySession
{
    $product = LoanProduct::factory()->create();
    $definition = JourneyDefinition::create(['loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active]);
    $session = JourneySession::create(['loan_product_id' => $product->id, 'journey_definition_id' => $definition->id]);

    foreach ($responses as $key => $value) {
        JourneyResponse::create(['journey_session_id' => $session->id, 'field_key' => $key, 'value' => $value]);
    }

    return $session;
}

it('computes age from date of birth', function () {
    $session = sessionWithResponses(['date_of_birth' => now()->subYears(30)->subDays(1)->toDateString()]);

    $profile = app(ProfileNormalizer::class)->normalize($session);

    expect($profile->age)->toBe(30);
});

it('sums monthly and other income into a total', function () {
    $session = sessionWithResponses(['monthly_income' => '60000', 'other_monthly_income' => '5000']);

    $profile = app(ProfileNormalizer::class)->normalize($session);

    expect($profile->monthlyIncome)->toBe(60000.0)
        ->and($profile->otherMonthlyIncome)->toBe(5000.0)
        ->and($profile->totalMonthlyIncome)->toBe(65000.0);
});

it('falls back to zero total income when nothing was answered', function () {
    $session = sessionWithResponses([]);

    $profile = app(ProfileNormalizer::class)->normalize($session);

    expect($profile->monthlyIncome)->toBeNull()
        ->and($profile->totalMonthlyIncome)->toBe(0.0);
});

it('only counts existing EMI amount when has_existing_emis is yes', function () {
    $saidNo = app(ProfileNormalizer::class)->normalize(
        sessionWithResponses(['has_existing_emis' => 'no', 'existing_emi_amount' => '15000']),
    );
    $saidYes = app(ProfileNormalizer::class)->normalize(
        sessionWithResponses(['has_existing_emis' => 'yes', 'existing_emi_amount' => '15000']),
    );

    expect($saidNo->hasExistingEmis)->toBeFalse()
        ->and($saidNo->existingEmiAmount)->toBe(0.0)
        ->and($saidYes->hasExistingEmis)->toBeTrue()
        ->and($saidYes->existingEmiAmount)->toBe(15000.0);
});

it('resolves employer name from either salaried or self-employed fields', function () {
    $salaried = app(ProfileNormalizer::class)->normalize(sessionWithResponses(['company_name' => 'Acme Corp']));
    $selfEmployed = app(ProfileNormalizer::class)->normalize(sessionWithResponses(['business_name' => 'Rao Textiles']));

    expect($salaried->employerName)->toBe('Acme Corp');
    expect($selfEmployed->employerName)->toBe('Rao Textiles');
});

it('exposes product-specific fields through the raw fallback', function () {
    $session = sessionWithResponses(['property_value' => '5000000', 'property_type' => 'apartment']);

    $profile = app(ProfileNormalizer::class)->normalize($session);

    expect($profile->get('property_value'))->toBe('5000000');
    expect($profile->get('nonexistent_key', 'default'))->toBe('default');
});
