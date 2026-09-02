<?php

use App\Modules\CreditBureau\Actions\RunCreditBureauCheck;
use App\Modules\CreditBureau\Contracts\CreditBureauProvider;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Providers\DemoCreditBureauProvider;
use App\Modules\CreditBureau\Providers\NullCreditBureauProvider;

it('records a failed check with no score when the Null provider is bound', function () {
    $this->app->bind(CreditBureauProvider::class, NullCreditBureauProvider::class);
    $consent = CreditConsent::factory()->create();

    $check = app(RunCreditBureauCheck::class)->handle($consent);

    expect($check->credit_consent_id)->toBe($consent->id);
    expect($check->provider)->toBe('none');
    expect($check->status)->toBe(CreditCheckStatus::Failed);
    expect($check->score)->toBeNull();
    expect($check->completed_at)->not->toBeNull();
});

it('records a completed check with a score when a demo provider is opted into', function () {
    $this->app->bind(CreditBureauProvider::class, DemoCreditBureauProvider::class);
    $consent = CreditConsent::factory()->create(['pan_number' => 'ABCDE1234F']);

    $check = app(RunCreditBureauCheck::class)->handle($consent);

    expect($check->provider)->toBe('demo');
    expect($check->status)->toBe(CreditCheckStatus::Completed);
    expect($check->score)->toBeGreaterThanOrEqual(650)->toBeLessThanOrEqual(900);
});
