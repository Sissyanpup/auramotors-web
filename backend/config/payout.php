<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disbursement Gateway Driver
    |--------------------------------------------------------------------------
    |
    | "manual" records a payout the admin already sent outside the app (bank
    | transfer done by hand) — no outbound network call, works in the fully
    | offline classroom environment. "xendit" calls the real Xendit
    | Disbursement API and requires internet access.
    |
    */

    'driver' => env('PAYOUT_GATEWAY_DRIVER', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | Platform Commission Rate
    |--------------------------------------------------------------------------
    |
    | Fraction of the transaction amount kept by the platform before the rest
    | is disbursed to the seller. 0.03 = 3%.
    |
    */

    'commission_rate' => (float) env('PLATFORM_COMMISSION_RATE', 0.03),

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
    ],

];
