<?php

namespace App\Enums;

enum PayoutMethod: string
{
    case Manual = 'manual';
    case Xendit = 'xendit';
}
