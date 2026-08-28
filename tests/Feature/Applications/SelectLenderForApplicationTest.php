<?php

use App\Models\LenderProduct;
use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Applications\Actions\SelectLenderForApplication;
use App\Modules\Applications\Enums\ApplicationStatus;
use App\Modules\Applications\Models\Application;
use App\Modules\Customers\Models\Customer;
use App\Modules\Eligibility\Enums\EligibilityStatus;
use App\Modules\Eligibility\Models\EligibilityResult;
use App\Modules\Journey\Models\JourneySession;

it('creates an application for an eligible result', function () {
    $customer = Customer::factory()->create();
    $session = JourneySession::factory()->create(['customer_id' => $customer->id]);
    $lenderProduct = LenderProduct::factory()->create();
    $result = EligibilityResult::factory()->create([
        'journey_session_id' => $session->id,
        'lender_product_id' => $lenderProduct->id,
        'status' => EligibilityStatus::Eligible,
    ]);

    $application = app(SelectLenderForApplication::class)->handle($result);

    expect($application)->not->toBeNull();
    expect($application->customer_id)->toBe($customer->id);
    expect($application->lender_product_id)->toBe($lenderProduct->id);
    expect($application->status)->toBe(ApplicationStatus::LenderSelected);
});

it('allows selecting a conditional result', function () {
    $result = EligibilityResult::factory()->create(['status' => EligibilityStatus::Conditional]);

    $application = app(SelectLenderForApplication::class)->handle($result);

    expect($application)->not->toBeNull();
});

it('refuses to create an application for a not-eligible result', function () {
    $result = EligibilityResult::factory()->create(['status' => EligibilityStatus::NotEligible]);

    $application = app(SelectLenderForApplication::class)->handle($result);

    expect($application)->toBeNull();
});

it('is idempotent for the same session and lender product', function () {
    $session = JourneySession::factory()->create();
    $lenderProduct = LenderProduct::factory()->create();
    $result = EligibilityResult::factory()->create([
        'journey_session_id' => $session->id,
        'lender_product_id' => $lenderProduct->id,
        'status' => EligibilityStatus::Eligible,
    ]);

    $first = app(SelectLenderForApplication::class)->handle($result);
    $second = app(SelectLenderForApplication::class)->handle($result);

    expect($second->id)->toBe($first->id);
    expect(Application::query()->count())->toBe(1);
});

it('tracks a lender_selected analytics event only once, not on the idempotent re-selection', function () {
    $result = EligibilityResult::factory()->create(['status' => EligibilityStatus::Eligible]);

    app(SelectLenderForApplication::class)->handle($result);
    app(SelectLenderForApplication::class)->handle($result);

    expect(AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::LenderSelected)->count())->toBe(1);
});
