<?php

use App\Modules\CreditScore\Actions\VerifyMobileOtp;
use App\Modules\CreditScore\Models\MobileOtpChallenge;
use Illuminate\Support\Facades\Hash;

it('verifies a correct, unexpired code', function () {
    $challenge = MobileOtpChallenge::factory()->create(['otp_hash' => Hash::make('123456')]);

    $result = (new VerifyMobileOtp)->handle($challenge, '123456');

    expect($result)->toBeTrue();
    expect($challenge->fresh()->verified_at)->not->toBeNull();
});

it('rejects an incorrect code and increments attempts', function () {
    $challenge = MobileOtpChallenge::factory()->create(['otp_hash' => Hash::make('123456')]);

    $result = (new VerifyMobileOtp)->handle($challenge, '000000');

    expect($result)->toBeFalse();
    expect($challenge->fresh()->attempts)->toBe(1);
    expect($challenge->fresh()->verified_at)->toBeNull();
});

it('rejects an expired code', function () {
    $challenge = MobileOtpChallenge::factory()->expired()->create(['otp_hash' => Hash::make('123456')]);

    expect((new VerifyMobileOtp)->handle($challenge, '123456'))->toBeFalse();
});

it('rejects a code once already verified', function () {
    $challenge = MobileOtpChallenge::factory()->verified()->create(['otp_hash' => Hash::make('123456')]);

    expect((new VerifyMobileOtp)->handle($challenge, '123456'))->toBeFalse();
});

it('fails closed after five wrong attempts, even with the correct code', function () {
    $challenge = MobileOtpChallenge::factory()->create(['otp_hash' => Hash::make('123456'), 'attempts' => 5]);

    expect((new VerifyMobileOtp)->handle($challenge, '123456'))->toBeFalse();
});
