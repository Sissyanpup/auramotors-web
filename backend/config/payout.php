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

    /*
    | Bonus keberhasilan menjual (catatan dosen 16 Okt). Dibayar platform dari
    | pendapatan komisi, ikut ditransfer bersama pencairan dana penjualan.
    | - sale_rate: bonus per unit terjual, persentase dari harga kendaraan.
    | - tiers: bonus satu kali saat jumlah penjualan sukses mencapai ambang.
    */
    'bonus' => [
        'sale_rate' => (float) env('SELLER_BONUS_SALE_RATE', 0.005),
        'tiers' => [
            3 => 2_500_000,
            5 => 5_000_000,
            10 => 15_000_000,
        ],
    ],

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
    ],

];
