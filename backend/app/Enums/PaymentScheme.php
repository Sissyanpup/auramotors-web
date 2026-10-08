<?php

namespace App\Enums;

enum PaymentScheme: string
{
    case DownPayment = 'down_payment';
    case Full = 'full';
}
