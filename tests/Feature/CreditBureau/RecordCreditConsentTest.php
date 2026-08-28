<?php

use App\Modules\CreditBureau\Actions\RecordCreditConsent;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Journey\Models\JourneySession;

it('records consent with the loan product name in the purpose', function () {
    $session = JourneySession::factory()->create();

    $consent = app(RecordCreditConsent::class)->handle($session, '198.51.100.1');

    expect($consent->purpose)->toContain($session->loanProduct->name);
    expect($consent->ip_address)->toBe('198.51.100.1');
    expect($consent->status)->toBe(ConsentStatus::Given);
    expect($consent->consented_at)->not->toBeNull();
});

it('is idempotent per session — calling twice does not create two consents', function () {
    $session = JourneySession::factory()->create();

    app(RecordCreditConsent::class)->handle($session, '198.51.100.1');
    app(RecordCreditConsent::class)->handle($session, '198.51.100.1');

    expect(CreditConsent::query()->where('journey_session_id', $session->id)->count())->toBe(1);
});

it('records the configured terms version', function () {
    config(['services.credit_bureau.terms_version' => 'v2-2026']);
    $session = JourneySession::factory()->create();

    $consent = app(RecordCreditConsent::class)->handle($session);

    expect($consent->terms_version)->toBe('v2-2026');
});
