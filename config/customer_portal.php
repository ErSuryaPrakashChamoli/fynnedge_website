<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product name
    |--------------------------------------------------------------------------
    |
    | The name the customer portal (/existing-customer, /my-profile) shows
    | customers. The public marketing site keeps its own branding settings.
    |
    */

    'brand' => env('CUSTOMER_PORTAL_BRAND', 'FynnOn by Markedge Technologies'),

    /*
    |--------------------------------------------------------------------------
    | Login throttling
    |--------------------------------------------------------------------------
    |
    | Wrong codes entered on /existing-customer, per IP address, before the
    | form locks for `login_decay_minutes`. Each code also has its own attempt
    | limit (mobile_otp.max_attempts), and sending codes is limited per number
    | and per IP inside RequestMobileOtp.
    |
    */

    'login_max_attempts' => (int) env('CUSTOMER_PORTAL_LOGIN_MAX_ATTEMPTS', 10),

    'login_decay_minutes' => (int) env('CUSTOMER_PORTAL_LOGIN_DECAY_MINUTES', 15),

];
