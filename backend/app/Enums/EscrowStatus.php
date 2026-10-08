<?php

namespace App\Enums;

enum EscrowStatus: string
{
    case EscrowHold = 'escrow_hold';
    case SerahTerima = 'serah_terima';
    case PayoutRelease = 'payout_release';
    case Selesai = 'selesai';
    case Dispute = 'dispute';
    case Refunded = 'refunded';
}
