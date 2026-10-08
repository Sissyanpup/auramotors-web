<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Draft = 'draft';
    case LogisticsPrep = 'logistics_prep';
    case InTransit = 'in_transit';
    case CustomsClearance = 'customs_clearance';
    case Delivered = 'delivered';
    case Delayed = 'delayed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::LogisticsPrep => 'Persiapan Logistik',
            self::InTransit => 'Dalam Perjalanan',
            self::CustomsClearance => 'Bea Cukai',
            self::Delivered => 'Diterima',
            self::Delayed => 'Tertunda',
        };
    }
}
