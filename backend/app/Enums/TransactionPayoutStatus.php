<?php

namespace App\Enums;

enum TransactionPayoutStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
}
