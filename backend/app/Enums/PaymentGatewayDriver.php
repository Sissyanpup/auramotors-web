<?php

namespace App\Enums;

enum PaymentGatewayDriver: string
{
    case Mock = 'mock';
    case Xendit = 'xendit';
}
