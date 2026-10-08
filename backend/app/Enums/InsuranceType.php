<?php

namespace App\Enums;

enum InsuranceType: string
{
    case None = 'none';
    case Tlo = 'tlo';
    case AllRisk = 'all_risk';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tanpa Asuransi',
            self::Tlo => 'TLO (Total Loss Only)',
            self::AllRisk => 'All Risk',
        };
    }
}
