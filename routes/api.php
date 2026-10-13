<?php

use App\Http\Controllers\Api\V1\MobileOtpController;
use Illuminate\Support\Facades\Route;

/*
 * Stateless JSON API, served under /api (bootstrap/app.php). Public by design:
 * no session, no CSRF and no authentication — every endpoint here must be safe
 * for an anonymous caller, which is why each one carries its own throttle.
 */
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    /*
     * Mobile OTP. See docs/mobile-otp-integration.md. Sending texts a real phone,
     * so it is throttled per IP here and per number + per IP inside
     * RequestMobileOtp, with a resend cooldown on top.
     */
    Route::post('/mobile-otp/send', [MobileOtpController::class, 'send'])
        ->middleware('throttle:mobile-otp-send')
        ->name('mobile-otp.send');

    Route::post('/mobile-otp/verify', [MobileOtpController::class, 'verify'])
        ->middleware('throttle:mobile-otp-verify')
        ->name('mobile-otp.verify');
});
