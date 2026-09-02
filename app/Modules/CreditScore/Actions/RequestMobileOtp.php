<?php

namespace App\Modules\CreditScore\Actions;

use App\Modules\CreditScore\Models\MobileOtpChallenge;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class RequestMobileOtp
{
    private const MAX_REQUESTS_PER_HOUR = 5;

    /**
     * Livewire component actions hit Livewire's own update endpoint, not a named
     * route, so the throttle:public-forms middleware on the page route never sees
     * these calls — the rate limit has to live here instead.
     *
     * @return array{challenge: MobileOtpChallenge, code: string}
     */
    public function handle(string $mobileNumber, ?string $ipAddress = null): array
    {
        $key = "otp-request:{$mobileNumber}";

        if (RateLimiter::tooManyAttempts($key, self::MAX_REQUESTS_PER_HOUR)) {
            throw ValidationException::withMessages([
                'mobileNumber' => 'Too many OTP requests for this number. Please try again in a while.',
            ]);
        }

        RateLimiter::hit($key, 3600);

        $code = (string) random_int(100000, 999999);

        $challenge = MobileOtpChallenge::query()->create([
            'mobile_number' => $mobileNumber,
            'otp_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'ip_address' => $ipAddress,
        ]);

        return ['challenge' => $challenge, 'code' => $code];
    }
}
