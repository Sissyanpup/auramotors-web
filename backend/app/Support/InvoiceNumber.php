<?php

namespace App\Support;

use App\Models\Transaction;
use Illuminate\Support\Str;

/**
 * Generator nomor invoice mengikuti pola industri retail/otomotif Indonesia:
 *   {PREFIX}/{YYYYMM}/{TRANSACTION_ID}-{RAND4}
 * mis. INV/202609/00042-8G3A
 *
 * Kombinasi id transaksi + random 4-char membuat nomor bersifat unik
 * per bulan dan tetap mudah dilacak ke row transaksi asalnya.
 */
class InvoiceNumber
{
    public static function generate(Transaction $transaction): string
    {
        $prefix = config('payment.invoice.prefix', 'INV');
        $period = now()->format('Ym');
        $sequence = str_pad((string) $transaction->id, 5, '0', STR_PAD_LEFT);
        $random = Str::upper(Str::random(4));

        return sprintf('%s/%s/%s-%s', $prefix, $period, $sequence, $random);
    }
}
