<?php

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Providers\NullCreditBureauProvider;

it('never fabricates a score and always reports as not configured', function () {
    $provider = new NullCreditBureauProvider;
    $consent = new CreditConsent;

    $result = $provider->check($consent);

    expect($result->status)->toBe(CreditCheckStatus::Failed);
    expect($result->score)->toBeNull();
    expect($result->failureReason)->not->toBeNull();
});

it('identifies itself as no provider', function () {
    expect((new NullCreditBureauProvider)->name())->toBe('none');
});
