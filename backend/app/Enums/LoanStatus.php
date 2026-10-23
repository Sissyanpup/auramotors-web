<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case PaidOff = 'paid_off';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Sedang dianalisis leasing',
            self::Approved => 'Disetujui — cicilan berjalan',
            self::PaidOff => 'Lunas',
        };
    }
}
