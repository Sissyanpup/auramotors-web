<?php

namespace App\Enums;

enum VinCheckStatus: string
{
    case Clean = 'clean';
    case Warning = 'warning';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Clean => 'Bersih',
            self::Warning => 'Perlu Perhatian',
            self::Blocked => 'Diblokir',
        };
    }
}
