<?php

namespace App\Enums;

enum VehiclePolicyType: string
{
    case AllRisk = 'all_risk';
    case Tlo = 'tlo';
    case AgreedValue = 'agreed_value';

    public function label(): string
    {
        return match ($this) {
            self::AllRisk => 'All Risk / Comprehensive',
            self::Tlo => 'TLO (Total Loss Only)',
            self::AgreedValue => 'Agreed Value',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AllRisk => 'Perlindungan penuh: kerusakan ringan, berat, pencurian, tanggung jawab pihak ketiga.',
            self::Tlo => 'Hanya jika kendaraan hilang atau rusak >75% (kolektor / garage queen).',
            self::AgreedValue => 'Nilai ganti rugi disepakati di awal (kendaraan klasik / exotic yang nilainya stabil/naik).',
        };
    }
}
