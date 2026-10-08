<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Driver
    |--------------------------------------------------------------------------
    |
    | "mock" simulates invoices entirely inside the app (no outbound network
    | call) — used for the classroom demo, which runs fully offline. "xendit"
    | calls the real Xendit sandbox API and requires internet access.
    |
    */

    'gateway' => env('PAYMENT_GATEWAY_DRIVER', 'mock'),

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
        'callback_verification_token' => env('XENDIT_CALLBACK_VERIFICATION_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Nota / Invoice
    |--------------------------------------------------------------------------
    |
    | Rekening virtual escrow platform yang ditampilkan di nota resmi kepada
    | buyer. Nomor invoice mengikuti format INV/YYYYMM/ID-XXXXX yang cukup
    | umum di ritel/otomotif Indonesia.
    |
    */

    'invoice' => [
        'prefix' => env('INVOICE_PREFIX', 'INV'),
    ],

    'escrow_bank' => [
        'bank' => env('ESCROW_BANK_NAME', 'BCA'),
        'account_number' => env('ESCROW_BANK_ACCOUNT', '1234567890'),
        'account_holder' => env('ESCROW_BANK_HOLDER', 'PT AURAMOTORS ESCROW INDONESIA'),
    ],

];
