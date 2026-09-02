<?php

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\CreditScore\Providers\DemoCreditScoreProvider;

it('returns a completed result with a score in the demo range', function () {
    $check = new CreditScoreCheck(['pan_number' => 'ABCDE1234F']);

    $result = (new DemoCreditScoreProvider)->check($check);

    expect($result->status)->toBe(CreditCheckStatus::Completed);
    expect($result->score)->toBeGreaterThanOrEqual(650)->toBeLessThanOrEqual(900);
});

it('returns the same score for the same PAN every time', function () {
    $checkA = new CreditScoreCheck(['pan_number' => 'ABCDE1234F']);
    $checkB = new CreditScoreCheck(['pan_number' => 'ABCDE1234F']);

    $provider = new DemoCreditScoreProvider;

    expect($provider->check($checkA)->score)->toBe($provider->check($checkB)->score);
});

it('returns different scores for different PANs', function () {
    $checkA = new CreditScoreCheck(['pan_number' => 'ABCDE1234F']);
    $checkB = new CreditScoreCheck(['pan_number' => 'ZYXWV9876Q']);

    $provider = new DemoCreditScoreProvider;

    expect($provider->check($checkA)->score)->not->toBe($provider->check($checkB)->score);
});

it('identifies itself as demo', function () {
    expect((new DemoCreditScoreProvider)->name())->toBe('demo');
});
