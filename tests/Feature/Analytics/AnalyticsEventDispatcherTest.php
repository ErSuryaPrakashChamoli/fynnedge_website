<?php

use App\Models\LenderProduct;
use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Analytics\Services\AnalyticsEventDispatcher;
use App\Modules\Journey\Models\JourneySession;

it('records a session-scoped event with its loan product denormalized', function () {
    $session = JourneySession::factory()->create();

    $event = app(AnalyticsEventDispatcher::class)->track(AnalyticsEventKey::JourneyStarted, session: $session);

    expect($event->event_key)->toBe(AnalyticsEventKey::JourneyStarted);
    expect($event->journey_session_id)->toBe($session->id);
    expect($event->loan_product_id)->toBe($session->loan_product_id);
    expect($event->lender_product_id)->toBeNull();
});

it('records a lender-product-scoped event and derives the loan product from it when no session is given', function () {
    $lenderProduct = LenderProduct::factory()->create();

    $event = app(AnalyticsEventDispatcher::class)->track(AnalyticsEventKey::EligibilityEvaluated, lenderProduct: $lenderProduct, properties: ['status' => 'eligible']);

    expect($event->lender_product_id)->toBe($lenderProduct->id);
    expect($event->loan_product_id)->toBe($lenderProduct->loan_product_id);
    expect($event->properties)->toBe(['status' => 'eligible']);
});

it('stores properties as an array and does not track updated_at', function () {
    AnalyticsEvent::factory()->create(['event_key' => AnalyticsEventKey::DocumentUploaded, 'properties' => ['document_type_id' => 4]]);

    $event = AnalyticsEvent::query()->first();

    expect($event->properties)->toBe(['document_type_id' => 4]);
    expect(AnalyticsEvent::UPDATED_AT)->toBeNull();
});
