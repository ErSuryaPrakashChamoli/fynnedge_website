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

    /*
    |--------------------------------------------------------------------------
    | How often a new credit report may be generated
    |--------------------------------------------------------------------------
    |
    | User decision (2026-10-17). Every check is free (the Surepass agreement
    | does not allow reselling reports). A person gets one NEW bureau report
    | every `every_months`, and at most `max_per_year` in any 12 months —
    | counted per customer and per PAN, across all bureaus. In between, a
    | check on the same bureau shows the saved score; any other is refused
    | before the bureau is asked. See CreditReportRegenerationLimit.
    |
    */

    'regeneration' => [
        'every_months' => (int) env('CREDIT_REPORT_REGENERATE_EVERY_MONTHS', 3),
        'max_per_year' => (int) env('CREDIT_REPORT_MAX_PER_YEAR', 4),
    ],

];
