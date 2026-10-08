<?php

namespace App\Enums;

enum BuyerIdType: string
{
    case Ktp = 'ktp';
    case Passport = 'passport';
}
