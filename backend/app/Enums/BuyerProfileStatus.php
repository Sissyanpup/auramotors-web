<?php

namespace App\Enums;

enum BuyerProfileStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
