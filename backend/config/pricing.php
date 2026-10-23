<?php

/*
|--------------------------------------------------------------------------
| Rincian Harga, Pajak, Asuransi & Simulasi Kredit
|--------------------------------------------------------------------------
|
| Semua tarif di file ini adalah NILAI SIMULASI untuk keperluan tugas kuliah
| (proyek offline). Angka disusun mendekati praktik umum di Indonesia supaya
| breakdown terasa realistis, tetapi BUKAN tarif resmi Samsat/OJK/DJP.
| Dipakai oleh App\Support\PriceBreakdownCalculator — satu-satunya sumber
| perhitungan untuk halaman checkout, detail transaksi, dan PDF.
|
*/

return [

    /*
    | Komposisi pajak yang "terkandung" di harga jual kendaraan (sama dengan
    | Tax Invoice). Harga listing dianggap sudah termasuk PPN & PPnBM.
    */
    'vehicle_tax' => [
        'ppn_rate' => 0.11,
        'ppnbm_rate' => 0.20,
    ],

    /*
    | Biaya balik nama & registrasi — dibayar pembeli langsung ke Samsat,
    | TIDAK ditagih lewat escrow. Ditampilkan supaya pembeli tahu total biaya
    | kepemilikan sebenarnya.
    |
    | NJKB (Nilai Jual Kendaraan Bermotor) disimulasikan = harga × njkb_ratio.
    */
    'registration' => [
        'njkb_ratio' => 0.70,
        'bbnkb_rate' => 0.01,
        'pkb_rate' => 0.02,
        'swdkllj' => 143_000,
        'stnk_fee' => 200_000,
        'tnkb_fee' => 100_000,
        'bpkb_fee' => 375_000,
        'physical_check_fee' => 500_000,
    ],

    /*
    | Asuransi kendaraan. Premi dasar mengikuti opsi listing (seller boleh
    | override persentase). Perluasan jaminan (rider) dihitung dari harga
    | pertanggungan (TSI = harga kendaraan) atau flat.
    */
    'insurance' => [
        'insurer_name' => env('INSURER_NAME', 'PT Asuransi Aura Proteksi (Simulasi)'),
        'policy_fee' => 50_000,
        'stamp_duty' => 10_000,
        'deductible_per_claim' => 300_000,
        'theft_settlement_percent' => 80,
        'total_loss_threshold_percent' => 75,

        'addons' => [
            'flood' => [
                'label' => 'Banjir & Angin Topan',
                'description' => 'Kerusakan akibat banjir, termasuk mesin terendam, dan angin topan.',
                'rate_percent' => 0.075,
            ],
            'earthquake' => [
                'label' => 'Gempa Bumi, Tsunami & Letusan Gunung',
                'description' => 'Kerusakan akibat bencana alam geologis.',
                'rate_percent' => 0.12,
            ],
            'riot' => [
                'label' => 'Huru-hara & Kerusuhan (SRCC)',
                'description' => 'Kerusakan akibat kerusuhan, pemogokan, dan huru-hara.',
                'rate_percent' => 0.05,
            ],
            'terrorism' => [
                'label' => 'Terorisme & Sabotase',
                'description' => 'Kerusakan akibat tindakan terorisme atau sabotase.',
                'rate_percent' => 0.05,
            ],
            'third_party_liability' => [
                'label' => 'Tanggung Jawab Hukum Pihak Ketiga (TJH s.d. Rp50 juta)',
                'description' => 'Ganti rugi kepada pihak lain (kendaraan/properti/cedera) yang dirugikan.',
                'flat_premium' => 500_000,
            ],
            'personal_accident' => [
                'label' => 'Kecelakaan Diri Pengemudi & Penumpang',
                'description' => 'Santunan cedera/meninggal pengemudi & 4 penumpang, s.d. Rp25 juta/orang.',
                'flat_premium' => 225_000,
            ],
            'towing_replacement' => [
                'label' => 'Derek, Bengkel Rekanan & Mobil Pengganti',
                'description' => 'Layanan derek 24 jam ke bengkel rekanan dan mobil pengganti selama perbaikan (maks. 7 hari).',
                'flat_premium' => 750_000,
                'only_for' => ['all_risk'],
            ],
        ],
    ],

    /*
    | Biaya layanan platform — dipotong dari dana escrow saat pencairan ke
    | penjual (lihat App\Payouts\PayoutService). Ditanggung penjual.
    */
    'stamp_duty_per_document' => 10_000,

    /*
    | Kredit kendaraan lewat leasing (simulasi). Tarif disusun dari kisaran
    | umum kredit mobil bekas di Indonesia (sekitar 7–9% flat/tahun untuk
    | tenor 5 tahun, lebih tinggi untuk unit tua) — revisi dosen 16 Okt 2026
    | karena tarif sebelumnya (5,5–7,5%) terlalu rendah.
    |
    | Alur: pembeli bayar DP + biaya kredit di muka ke escrow → leasing
    | menyetujui → leasing melunasi sisa harga ke penjual → pembeli mencicil
    | ke leasing (angsuran dibayar di belakang / ADDB, mulai bulan depan).
    */
    'credit' => [
        'lender_name' => env('CREDIT_LENDER_NAME', 'PT Aura Finance Indonesia (Simulasi)'),
        'min_dp_percent' => 20,
        'dp_options' => [20, 25, 30, 40, 50],

        // Bunga flat per tahun per tenor (bulan).
        'flat_rates' => [
            12 => 0.08,
            24 => 0.09,
            36 => 0.10,
            48 => 0.11,
            60 => 0.12,
        ],

        // Tambahan bunga untuk unit tua (umur = tahun berjalan − tahun produksi).
        'age_surcharges' => [
            ['min_age' => 11, 'rate' => 0.02],
            ['min_age' => 6, 'rate' => 0.01],
        ],

        'provision_rate' => 0.01,
        'admin_fee' => 1_500_000,

        // Jaminan fidusia: honor notaris + PNBP pendaftaran berjenjang menurut nilai pinjaman.
        'fiducia' => [
            'notary_fee' => 750_000,
            'pnbp_tiers' => [
                50_000_000 => 50_000,
                100_000_000 => 100_000,
                250_000_000 => 200_000,
                500_000_000 => 400_000,
                1_000_000_000 => 800_000,
            ],
            'pnbp_max' => 1_600_000,
        ],

        // Denda keterlambatan per hari dari nilai angsuran.
        'late_fee_daily_rate' => 0.002,

        // Jeda persetujuan otomatis leasing (detik) — seolah-olah sedang dianalisis.
        'auto_approve_seconds' => (int) env('CREDIT_AUTO_APPROVE_SECONDS', 60),
    ],

];
