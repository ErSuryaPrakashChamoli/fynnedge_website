<?php

use App\Modules\CreditScore\Actions\RequestMobileOtp;
use App\Modules\CreditScore\Models\MobileOtpChallenge;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

it('creates a challenge with a hashed code and a 10-minute expiry', function () {
    ['challenge' => $challenge, 'code' => $code] = (new RequestMobileOtp)->handle('9876543210', '127.0.0.1');

    expect($challenge)->toBeInstanceOf(MobileOtpChallenge::class);
    expect($challenge->mobile_number)->toBe('9876543210');
    expect(strlen($code))->toBe(6);
    expect(Hash::check($code, $challenge->otp_hash))->toBeTrue();
    expect($challenge->expires_at->diffInMinutes(now()))->toBeLessThanOrEqual(10);
    expect($challenge->ip_address)->toBe('127.0.0.1');
});

it('never stores the OTP in plaintext', function () {
    ['challenge' => $challenge, 'code' => $code] = (new RequestMobileOtp)->handle('9876543210');

    expect($challenge->otp_hash)->not->toBe($code);
});

it('throws after five requests for the same number within an hour', function () {
    $action = new RequestMobileOtp;

    for ($i = 0; $i < 5; $i++) {
        $action->handle('9876543210');
    }

    expect(fn () => $action->handle('9876543210'))->toThrow(ValidationException::class);
});

it('rate-limits per mobile number, not globally', function () {
    $action = new RequestMobileOtp;

    for ($i = 0; $i < 5; $i++) {
        $action->handle('9876543210');
    }

    // A different number is unaffected by the first number's limit.
    ['challenge' => $challenge] = $action->handle('9123456780');

    expect($challenge)->toBeInstanceOf(MobileOtpChallenge::class);
});
