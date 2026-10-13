<?php

use App\Modules\MobileOtp\Actions\VerifyMobileOtp;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\MobileOtpVerificationResult;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Support\Facades\Hash;

it('verifies a correct, unexpired code', function () {
    $challenge = MobileOtpChallenge::factory()->create(['otp_hash' => Hash::make('123456')]);

    $result = (new VerifyMobileOtp)->handle($challenge, '123456');

    expect($result)->toBe(MobileOtpVerificationResult::Verified);
    expect($challenge->fresh())
        ->status->toBe(MobileOtpStatus::Verified)
        ->verified_at->not->toBeNull()
        ->attempts->toBe(1);
});

it('verifies a code that starts with zero', function () {
    $challenge = MobileOtpChallenge::factory()->create(['otp_hash' => Hash::make('012345')]);

    expect((new VerifyMobileOtp)->handle($challenge, '012345'))->toBe(MobileOtpVerificationResult::Verified);
});

it('rejects an incorrect code and increments attempts', function () {
    $challenge = MobileOtpChallenge::factory()->create(['otp_hash' => Hash::make('123456')]);

    $result = (new VerifyMobileOtp)->handle($challenge, '000000');

    expect($result)->toBe(MobileOtpVerificationResult::Incorrect);
    expect($challenge->fresh())
        ->attempts->toBe(1)
        ->status->toBe(MobileOtpStatus::Pending)
        ->verified_at->toBeNull();
});

it('rejects an expired code without counting an attempt', function () {
    $challenge = MobileOtpChallenge::factory()->expired()->create(['otp_hash' => Hash::make('123456')]);

    expect((new VerifyMobileOtp)->handle($challenge, '123456'))->toBe(MobileOtpVerificationResult::Expired);
    expect($challenge->fresh())->attempts->toBe(0)->verified_at->toBeNull();
});

it('rejects a code once already verified', function () {
    $challenge = MobileOtpChallenge::factory()->verified()->create(['otp_hash' => Hash::make('123456')]);

    expect((new VerifyMobileOtp)->handle($challenge, '123456'))->toBe(MobileOtpVerificationResult::AlreadyVerified);
});

it('rejects a code that a newer code has superseded', function () {
    $challenge = MobileOtpChallenge::factory()->create(['otp_hash' => Hash::make('123456'), 'status' => MobileOtpStatus::Superseded]);

    expect((new VerifyMobileOtp)->handle($challenge, '123456'))->toBe(MobileOtpVerificationResult::Superseded);
    expect($challenge->fresh()->verified_at)->toBeNull();
});

it('fails closed after five wrong attempts, even with the correct code', function () {
    $challenge = MobileOtpChallenge::factory()->create(['otp_hash' => Hash::make('123456'), 'attempts' => 5]);

    expect((new VerifyMobileOtp)->handle($challenge, '123456'))->toBe(MobileOtpVerificationResult::AttemptsExhausted);
    expect($challenge->fresh())->status->toBe(MobileOtpStatus::Exhausted)->verified_at->toBeNull();
});

it('locks the challenge on the wrong attempt that reaches the limit', function () {
    $challenge = MobileOtpChallenge::factory()->create(['otp_hash' => Hash::make('123456'), 'attempts' => 4]);

    expect((new VerifyMobileOtp)->handle($challenge, '000000'))->toBe(MobileOtpVerificationResult::AttemptsExhausted);
    expect($challenge->fresh())->status->toBe(MobileOtpStatus::Exhausted)->attempts->toBe(5);
});

it('judges the stored row, not a stale copy, so a second simultaneous attempt cannot verify again', function () {
    $challenge = MobileOtpChallenge::factory()->create(['otp_hash' => Hash::make('123456')]);
    $staleCopy = MobileOtpChallenge::query()->findOrFail($challenge->id);

    $first = (new VerifyMobileOtp)->handle($challenge, '123456');
    $second = (new VerifyMobileOtp)->handle($staleCopy, '123456');

    expect($first)->toBe(MobileOtpVerificationResult::Verified)
        ->and($second)->toBe(MobileOtpVerificationResult::AlreadyVerified)
        ->and($staleCopy->status)->toBe(MobileOtpStatus::Verified)
        ->and($challenge->fresh()->attempts)->toBe(1);
});
