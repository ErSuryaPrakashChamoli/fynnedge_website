<?php

use App\Modules\CreditBureau\Providers\NullCreditBureauProvider;
use App\Modules\CreditScore\Providers\DemoCreditScoreProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'credit_bureau' => [
        // No real bureau is contracted yet — swap this to a concrete provider class
        // (implementing App\Modules\CreditBureau\Contracts\CreditBureauProvider) once
        // one is, and add its credentials here rather than hard-coding them anywhere.
        'provider' => env('CREDIT_BUREAU_PROVIDER', NullCreditBureauProvider::class),
        'terms_version' => env('CREDIT_BUREAU_TERMS_VERSION', 'v1'),
    ],

    'areach' => [
        // SMS gateway for mobile OTPs (App\Modules\MobileOtp\Providers\AreachSmsProvider).
        // The key is a server-side secret: set it in the server's environment only —
        // never in .env.example, Git, Blade, JavaScript or a log line.
        'base_url' => env('AREACH_BASE_URL', 'https://api.areach.in/v1'),
        'key' => env('AREACH_API_KEY'),
        'sender' => env('AREACH_SENDER', 'FYNNED'),
        'timeout' => (int) env('AREACH_SMS_TIMEOUT', 10),
    ],

    'credit_score' => [
        // Backs the public "check your free credit score" page. Demo-by-default —
        // unlike credit_bureau above — because that page's whole purpose today is
        // to show a sample score; swap this once a real bureau is contracted.
        'provider' => env('CREDIT_SCORE_PROVIDER', DemoCreditScoreProvider::class),
    ],

];
