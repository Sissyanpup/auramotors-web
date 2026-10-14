<?php

/*
|--------------------------------------------------------------------------
| Identitas Badan Hukum (untuk kop surat & tanda tangan kontrak)
|--------------------------------------------------------------------------
|
| Semua nama, nomor AHU, NIB, NPWP, dan nama pejabat di bawah ini FIKTIF
| (proyek tugas kuliah offline). Dipakai di PDF kontrak/surat supaya setiap
| dokumen punya pihak berbadan hukum yang jelas: platform escrow sebagai
| pihak ketiga, bank kustodian rekening escrow, dan perusahaan asuransi.
|
*/

return [

    'platform' => [
        'name' => env('LEGAL_PLATFORM_NAME', 'PT AuraMotors Escrow Indonesia'),
        'legal_form' => 'Perseroan Terbatas',
        'ahu_number' => 'AHU-0012345.AH.01.01.Tahun 2026',
        'nib' => '9120001234567',
        'npwp' => '01.234.567.8-012.000',
        'address' => 'Gedung Aura Tower Lt. 21, Jl. Jend. Sudirman Kav. 99, Jakarta Selatan 12190',
        'signatory_name' => 'Rangga Adiwijaya',
        'signatory_title' => 'Direktur Utama',
        'notary' => 'Notaris Dewi Kartika, S.H., M.Kn. — Akta No. 07 tanggal 2 Januari 2026',
    ],

    'bank' => [
        'name' => env('LEGAL_BANK_NAME', 'PT Bank Aura Nusantara Tbk (Simulasi)'),
        'division' => 'Divisi Escrow & Custodian Services',
        'address' => 'Menara Aura Nusantara, Jl. M.H. Thamrin No. 8, Jakarta Pusat 10230',
        'swift' => 'AURNIDJA',
        'signatory_name' => 'Melati Prameswari',
        'signatory_title' => 'Kepala Divisi Escrow',
        'license' => 'Terdaftar & diawasi OJK (simulasi) — Izin No. KEP-000/OJK/2026',
    ],

    'insurer' => [
        'name' => env('INSURER_NAME', 'PT Asuransi Aura Proteksi (Simulasi)'),
        'address' => 'Aura Proteksi Building, Jl. HR. Rasuna Said Kav. C-5, Jakarta Selatan 12920',
        'license' => 'Izin Usaha OJK (simulasi) No. KEP-111/NB.1/2026',
        'signatory_name' => 'Yudhistira Kurniawan',
        'signatory_title' => 'Kepala Underwriting Kendaraan',
        'hotline' => '1500-AURA (24 jam, simulasi)',
    ],

];
