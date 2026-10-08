<?php

namespace App\Enums;

enum ShippingMode: string
{
    case Sea = 'sea';
    case Air = 'air';
    case Land = 'land';

    public function label(): string
    {
        return match ($this) {
            self::Sea => 'Laut (Kontainer)',
            self::Air => 'Udara (Kargo)',
            self::Land => 'Darat (Truk)',
        };
    }
}
