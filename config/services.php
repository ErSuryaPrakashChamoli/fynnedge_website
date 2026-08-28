<?php

use App\Modules\CreditBureau\Providers\NullCreditBureauProvider;

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

];
