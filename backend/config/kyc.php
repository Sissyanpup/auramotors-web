<?php

// Threshold nilai transaksi (dalam Rupiah) di atas mana buyer wajib menyelesaikan
// KYC + Proof of Funds sebelum checkout. Default Rp500jt sesuai praktik AML
// untuk kendaraan mewah (docs/06-product-backlog.md Epic 6).
return [
    'high_value_threshold' => env('KYC_HIGH_VALUE_THRESHOLD', 500_000_000),
];
