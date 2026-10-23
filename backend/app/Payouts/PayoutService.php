<?php

namespace App\Payouts;

use App\Enums\EscrowStatus;
use App\Enums\PayoutMethod;
use App\Enums\TransactionPayoutStatus;
use App\Models\Transaction;
use App\Models\TransactionPayout;
use App\Models\TransactionStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Drives Epic 4 (docs/03-payment-escrow.md ยง7): the actual disbursement of
 * held funds to the seller, minus platform commission. Every attempt is
 * recorded as its own transaction_payouts row (not just a status flip),
 * which doubles as the source of truth for the admin reconciliation report.
 */
class PayoutService
{
    public function __construct(
        private readonly DisbursementGateway $gateway,
        private readonly SellerBonusService $bonuses,
    ) {}

    public function disburse(Transaction $transaction, User $admin, ?string $reference, ?string $note): TransactionPayout
    {
        abort_unless($transaction->escrow_status === EscrowStatus::PayoutRelease, 409, 'Transaksi belum berada di tahap payout release.');
        abort_if($transaction->payout_status === TransactionPayoutStatus::Paid, 409, 'Dana transaksi ini sudah dicairkan ke penjual.');

        return DB::transaction(function () use ($transaction, $admin, $reference, $note) {
            // Premi asuransi & biaya balik nama ikut dibayar buyer ke escrow tapi milik
            // asuradur dan Samsat, bukan penjual — jadi hanya porsi kendaraan (DP) yang
            // menjadi basis komisi & payout. Transaksi lama tanpa snapshot: amount − premi.
            $rate = (float) config('payout.commission_rate');
            $vehiclePortion = (float) ($transaction->price_breakdown['payment']['dp_amount']
                ?? (float) $transaction->amount - (float) ($transaction->insurance_premium ?? 0));
            $commissionAmount = round($vehiclePortion * $rate, 2);
            $payoutAmount = round($vehiclePortion - $commissionAmount, 2);

            $payout = TransactionPayout::create([
                'transaction_id' => $transaction->id,
                'method' => PayoutMethod::from(config('payout.driver')),
                'status' => TransactionPayoutStatus::Pending,
                'commission_rate' => $rate,
                'commission_amount' => $commissionAmount,
                'payout_amount' => $payoutAmount,
                'reference' => $reference,
                'initiated_by' => $admin->id,
            ]);

            $result = $this->gateway->disburse($payout);

            $payout->update([
                'status' => $result->status,
                'reference' => $result->reference ?? $payout->reference,
                'failure_reason' => $result->failureReason,
                'paid_at' => $result->status === TransactionPayoutStatus::Paid ? now() : null,
            ]);

            $transaction->update(['payout_status' => $result->status]);

            // Bonus penjual (per unit + tier) ikut dibayarkan bersama pencairan yang berhasil.
            $this->bonuses->grantForPayout($payout->fresh('transaction'));

            $summary = $result->status === TransactionPayoutStatus::Paid
                ? "Payout via {$payout->method->value} berhasil ({$payout->reference})."
                : "Payout via {$payout->method->value} gagal: {$result->failureReason}";

            TransactionStatusHistory::create([
                'transaction_id' => $transaction->id,
                'from_status' => EscrowStatus::PayoutRelease->value,
                'to_status' => EscrowStatus::PayoutRelease->value,
                'actor_id' => $admin->id,
                'note' => trim($summary.($note ? " Catatan: {$note}" : '')),
            ]);

            return $payout->fresh();
        });
    }
}
