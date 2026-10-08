<?php

namespace App\Enums;

enum TransactionPaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * Map a Xendit invoice "status" field to our own payment status.
     */
    public static function fromXenditStatus(string $xenditStatus): self
    {
        return match (strtoupper($xenditStatus)) {
            'PAID', 'SETTLED' => self::Paid,
            'EXPIRED' => self::Expired,
            default => self::Pending,
        };
    }
}
