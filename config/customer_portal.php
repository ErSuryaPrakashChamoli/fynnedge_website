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
    | Paid refreshes, plans and the wallet
    |--------------------------------------------------------------------------
    |
    | User decision (2026-10-17). A customer's first check is free, and again
    | once `free_check_every_months` have passed since their last free one
    | (per customer and per PAN). Every other new bureau pull is paid from the
    | wallet: `first_refresh_paise` for the customer's first paid refresh,
    | `refresh_paise` after that, unless a plan is active (each bureau once
    | every 30 days, no per-refresh charge). GST is added on top of every price.
    | All amounts in paise.
    |
    | OFF by default: until a payment gateway is configured nobody can top up,
    | so switching billing on would only block returning customers.
    |
    */

    'billing' => [
        'enabled' => (bool) env('CUSTOMER_BILLING_ENABLED', false),
        'gst_percent' => (float) env('CUSTOMER_BILLING_GST_PERCENT', 18),
        'first_refresh_paise' => (int) env('CUSTOMER_BILLING_FIRST_REFRESH_PAISE', 9900),
        'refresh_paise' => (int) env('CUSTOMER_BILLING_REFRESH_PAISE', 7900),
        'free_check_every_months' => (int) env('CUSTOMER_BILLING_FREE_EVERY_MONTHS', 6),
        'plans' => [
            'half_year' => (int) env('CUSTOMER_BILLING_HALF_YEAR_PLAN_PAISE', 15000),
            'annual' => (int) env('CUSTOMER_BILLING_ANNUAL_PLAN_PAISE', 25000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | UPI top-ups
    |--------------------------------------------------------------------------
    |
    | `gateway` names a class in `gateways` implementing
    | App\Modules\Wallet\Contracts\PaymentGateway. None is built yet, so
    | top-ups stay switched off (UnconfiguredPaymentGateway).
    |
    */

    'payments' => [
        'gateway' => env('CUSTOMER_PAYMENT_GATEWAY'),
        'gateways' => [],
        'top_up_min_paise' => 1000,
        'top_up_max_paise' => 1000000,
    ],

];
