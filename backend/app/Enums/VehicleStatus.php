<?php

namespace App\Enums;

enum VehicleStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Sold = 'sold';
}
