<?php

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Providers\DemoCreditBureauProvider;

it('returns a completed result with a score in the demo range', function () {
    $consent = new CreditConsent(['pan_number' => 'ABCDE1234F']);

    $result = (new DemoCreditBureauProvider)->check($consent);

    expect($result->status)->toBe(CreditCheckStatus::Completed);
    expect($result->score)->toBeGreaterThanOrEqual(650)->toBeLessThanOrEqual(900);
});

it('returns the same score for the same PAN every time', function () {
    $consentA = new CreditConsent(['pan_number' => 'ABCDE1234F']);
    $consentB = new CreditConsent(['pan_number' => 'ABCDE1234F']);

    $provider = new DemoCreditBureauProvider;

    expect($provider->check($consentA)->score)->toBe($provider->check($consentB)->score);
});

it('returns different scores for different PANs', function () {
    $consentA = new CreditConsent(['pan_number' => 'ABCDE1234F']);
    $consentB = new CreditConsent(['pan_number' => 'ZYXWV9876Q']);

    $provider = new DemoCreditBureauProvider;

    expect($provider->check($consentA)->score)->not->toBe($provider->check($consentB)->score);
});

it('fails when no PAN was recorded with the consent', function () {
    $consent = new CreditConsent(['pan_number' => null]);

    $result = (new DemoCreditBureauProvider)->check($consent);

    expect($result->status)->toBe(CreditCheckStatus::Failed);
    expect($result->score)->toBeNull();
});

it('identifies itself as demo', function () {
    expect((new DemoCreditBureauProvider)->name())->toBe('demo');
});
