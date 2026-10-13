<?php

use App\Modules\MobileOtp\Providers\AreachSmsProvider;
use App\Modules\MobileOtp\Providers\LogSmsProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | SMS provider
    |--------------------------------------------------------------------------
    |
    | Which gateway texts the code. "areach" is the real one (credentials live in
    | services.areach). "log" writes the message to the application log instead
    | and only works when APP_ENV=local — anywhere else it refuses to send, so a
    | production server can never quietly fall back to it.
    |
    | To add a gateway: implement App\Modules\MobileOtp\Contracts\SmsProvider and
    | register it in `providers` below. Nothing else names a provider.
    |
    */

    'sms_provider' => env('MOBILE_OTP_SMS_PROVIDER', 'areach'),

    'providers' => [
        'areach' => AreachSmsProvider::class,
        'log' => LogSmsProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Code policy
    |--------------------------------------------------------------------------
    */

    // Digits in each code. RequestMobileOtp clamps this to 4–8.
    'length' => (int) env('MOBILE_OTP_LENGTH', 6),

    'expiry_minutes' => (int) env('MOBILE_OTP_EXPIRY_MINUTES', 10),

    // Wrong guesses allowed per challenge before it is locked as exhausted.
    'max_attempts' => (int) env('MOBILE_OTP_MAX_ATTEMPTS', 5),

    // Minimum gap between two codes for the same number and purpose.
    'resend_cooldown_seconds' => (int) env('MOBILE_OTP_RESEND_COOLDOWN_SECONDS', 60),

    // Codes per number per rolling hour, across every purpose — the SMS-bombing guard.
    'max_sends_per_number_per_hour' => (int) env('MOBILE_OTP_MAX_SENDS_PER_NUMBER_PER_HOUR', 5),

    // Codes per client IP per rolling hour. Applies to Livewire callers too,
    // which never pass through a route's throttle middleware.
    'max_sends_per_ip_per_hour' => (int) env('MOBILE_OTP_MAX_SENDS_PER_IP_PER_HOUR', 20),

    // How long a verified challenge may still be redeemed by the form it protects.
    'verified_ttl_minutes' => (int) env('MOBILE_OTP_VERIFIED_TTL_MINUTES', 30),

    /*
    | The SMS text. :code and :minutes are replaced. In India this must match the
    | DLT-registered template for the sender ID character for character, or the
    | operator drops the message — change it only together with the template.
    */
    'message' => env(
        'MOBILE_OTP_SMS_TEMPLATE',
        'Welcome to FynnEdge! Your verification code is :code. Enter it to securely continue your application. Valid for :minutes minutes. Never share your code.',
    ),

    /*
    |--------------------------------------------------------------------------
    | Live smoke test
    |--------------------------------------------------------------------------
    |
    | `php artisan mobile-otp:smoke-test` sends ONE real SMS and refuses to run
    | unless this is true. Switch it on for the test, then off again.
    |
    */

    'live_test_enabled' => (bool) env('MOBILE_OTP_LIVE_TEST_ENABLED', false),

];
