<?php

namespace App\Enums;

enum ActivityCategory: string
{
    case Account = 'account';
    case Verification = 'verification';
    case Transaction = 'transaction';
    case Payment = 'payment';
    case Handover = 'handover';
    case Document = 'document';
    case Shipment = 'shipment';
    case Credit = 'credit';
    case Payout = 'payout';
    case Bonus = 'bonus';
    case Promo = 'promo';

    public function label(): string
    {
        return match ($this) {
            self::Account => 'Akun',
            self::Verification => 'Verifikasi',
            self::Transaction => 'Transaksi',
            self::Payment => 'Pembayaran',
            self::Handover => 'Serah Terima',
            self::Document => 'Dokumen',
            self::Shipment => 'Pengiriman',
            self::Credit => 'Kredit',
            self::Payout => 'Payout',
            self::Bonus => 'Bonus',
            self::Promo => 'Promo',
        };
    }
}
