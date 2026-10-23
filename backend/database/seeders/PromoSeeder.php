<?php

namespace Database\Seeders;

use App\Models\Promo;
use Illuminate\Database\Seeder;

/**
 * Promo contoh untuk demo: satu harga coret otomatis dan dua voucher.
 */
class PromoSeeder extends Seeder
{
    public function run(): void
    {
        $promos = [
            [
                'name' => 'Flash Sale Akhir Bulan',
                'kind' => Promo::KIND_AUTO,
                'benefit' => Promo::BENEFIT_DISCOUNT,
                'discount_type' => 'percent',
                'discount_value' => 2,
                'max_discount' => 15_000_000,
                'badge' => 'Flash Sale',
                'description' => 'Diskon 2% untuk semua unit terverifikasi, ditanggung AuraMotors.',
                'ends_at' => now()->addDays(10),
            ],
            [
                'name' => 'Voucher Hemat Rp5 Juta',
                'kind' => Promo::KIND_VOUCHER,
                'code' => 'AURAHEMAT',
                'benefit' => Promo::BENEFIT_DISCOUNT,
                'discount_type' => 'fixed',
                'discount_value' => 5_000_000,
                'min_vehicle_price' => 300_000_000,
                'badge' => 'Voucher',
                'description' => 'Potongan langsung untuk kendaraan mulai Rp300 juta.',
                'quota' => 50,
                'ends_at' => now()->addMonth(),
            ],
            [
                'name' => 'Bunga Spesial Kredit',
                'kind' => Promo::KIND_VOUCHER,
                'code' => 'KREDITHEMAT',
                'benefit' => Promo::BENEFIT_CREDIT_RATE,
                'rate_cut' => 0.015,
                'badge' => 'Kredit',
                'description' => 'Bunga kredit leasing turun 1,5% per tahun selama tenor.',
                'ends_at' => now()->addMonth(),
            ],
            [
                'name' => 'Gratis Proteksi Banjir',
                'kind' => Promo::KIND_VOUCHER,
                'code' => 'ANTIBANJIR',
                'benefit' => Promo::BENEFIT_FREE_ADDON,
                'addon_code' => 'flood',
                'badge' => 'Asuransi',
                'description' => 'Perluasan banjir & angin topan gratis tahun pertama.',
                'ends_at' => now()->addMonth(),
            ],
        ];

        foreach ($promos as $promo) {
            Promo::query()->updateOrCreate(['name' => $promo['name']], [...$promo, 'starts_at' => now()->subDay(), 'is_active' => true]);
        }
    }
}
