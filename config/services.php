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

    'google_translate' => [
        // Google Cloud Translation (v2, API key) — fills the Hindi version of
        // content saved in the admin (App\Support\Localization\AutoTranslation).
        // Server-side secret: set it in the server's environment only. Without
        // it nothing is sent anywhere and untranslated content shows in English.
        'key' => env('GOOGLE_TRANSLATE_API_KEY'),
        'endpoint' => env('GOOGLE_TRANSLATE_ENDPOINT', 'https://translation.googleapis.com/language/translate/v2'),
        'timeout' => (int) env('GOOGLE_TRANSLATE_TIMEOUT', 30),
    ],

    'credit_score' => [
        // Backs the public "check your free credit score" page. Demo-by-default —
        // unlike credit_bureau above — because that page's whole purpose today is
        // to show a sample score; swap this once a real bureau is contracted.
        'provider' => env('CREDIT_SCORE_PROVIDER', DemoCreditScoreProvider::class),
    ],

    'surepass' => [
        // Surepass KYC API — the CRIF credit report integration
        // (App\Modules\CreditBureau\Services\SurepassCrifService, docs/crif-credit-report-integration.md).
        // The bearer token is a server-side secret: set it in the server's environment
        // only — never in .env.example, Git, Blade, JavaScript, a Setting or a log line.
        // Every report fetch is a PAID call, so there is deliberately no retry setting:
        // a timed-out request goes to reconciliation instead of being sent again.
        'base_url' => env('SUREPASS_BASE_URL', 'https://kyc-api.surepass.app'),
        'token' => env('SUREPASS_BEARER_TOKEN'),
        'timeout' => (int) env('SUREPASS_TIMEOUT', 60),
        'connect_timeout' => (int) env('SUREPASS_CONNECT_TIMEOUT', 10),
        // Which endpoint the public Free CRIF Score page reads the score from:
        // "report" (credit-report-crif/fetch-report — the score is taken from the
        // full report and the rest discarded) or "score" (credit-report-crif/score,
        // cheaper, but only if your Surepass plan includes it). `php artisan
        // crif:check-access` shows which ones your token may use.
        'crif_public_score_source' => env('SUREPASS_CRIF_PUBLIC_SCORE_SOURCE', 'report'),
    ],

];
