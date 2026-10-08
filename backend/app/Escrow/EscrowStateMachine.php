<?php

namespace App\Escrow;

use App\Enums\EscrowStatus;
use App\Enums\TransactionPayoutStatus;
use App\Enums\VehicleStatus;
use App\Models\Transaction;
use App\Models\TransactionStatusHistory;
use App\Models\User;

/**
 * Drives the escrow state machine documented in docs/03-payment-escrow.md:
 * escrow_hold -> serah_terima -> payout_release -> selesai, with an optional
 * dispute branch back to either a resumed state or a refund.
 *
 * Every transition is written to transaction_status_histories as an
 * append-only audit trail (who triggered it, and when) instead of only
 * overwriting the transaction's current status column.
 */
class EscrowStateMachine
{
    /**
     * Move a freshly-paid transaction into escrow: funds are now held by the
     * platform pending physical handover, and the vehicle is taken off the
     * public catalog as sold.
     */
    public function holdFunds(Transaction $transaction): void
    {
        if ($transaction->escrow_status !== null) {
            return;
        }

        $transaction->update(['escrow_status' => EscrowStatus::EscrowHold]);
        $transaction->vehicle()->update(['status' => VehicleStatus::Sold]);

        $this->recordHistory($transaction, null, EscrowStatus::EscrowHold, null, 'Pembayaran lunas, dana ditahan platform (escrow).');
    }

    /**
     * Buyer or seller confirms their side of the physical handover. Does not
     * transition the escrow state by itself — an admin still has to approve
     * that transition once both sides have confirmed.
     */
    public function confirmHandover(Transaction $transaction, User $actor): void
    {
        abort_unless($transaction->escrow_status === EscrowStatus::EscrowHold, 409, 'Transaksi belum berada di tahap penahanan dana (escrow).');

        if ($actor->id === $transaction->buyer_id) {
            abort_if($transaction->buyer_confirmed_at !== null, 409, 'Anda sudah mengonfirmasi serah-terima sebelumnya.');
            $transaction->update(['buyer_confirmed_at' => now()]);

            return;
        }

        if ($actor->id === $transaction->seller_id) {
            abort_if($transaction->seller_confirmed_at !== null, 409, 'Anda sudah mengonfirmasi serah-terima sebelumnya.');
            $transaction->update(['seller_confirmed_at' => now()]);

            return;
        }

        abort(403, 'Anda bukan pihak dalam transaksi ini.');
    }

    /**
     * Admin approves the escrow_hold -> serah_terima transition once both
     * buyer and seller have confirmed the handover.
     */
    public function approveHandover(Transaction $transaction, User $admin, ?string $note = null): void
    {
        abort_unless($transaction->escrow_status === EscrowStatus::EscrowHold, 409, 'Transaksi tidak berada di tahap penahanan dana.');
        abort_unless($transaction->buyer_confirmed_at && $transaction->seller_confirmed_at, 422, 'Konfirmasi serah-terima dari buyer & seller belum lengkap.');

        $transaction->update(['escrow_status' => EscrowStatus::SerahTerima]);

        $this->recordHistory($transaction, EscrowStatus::EscrowHold, EscrowStatus::SerahTerima, $admin, $note);
    }

    /**
     * Admin approves releasing the held funds to the seller (the actual
     * disbursement is Epic 4's scope — this only records the approval).
     */
    public function approvePayoutRelease(Transaction $transaction, User $admin, ?string $note = null): void
    {
        abort_unless($transaction->escrow_status === EscrowStatus::SerahTerima, 409, 'Transaksi belum berada di tahap serah-terima.');

        $transaction->update(['escrow_status' => EscrowStatus::PayoutRelease]);

        $this->recordHistory($transaction, EscrowStatus::SerahTerima, EscrowStatus::PayoutRelease, $admin, $note);
    }

    /**
     * Admin closes out the transaction after the payout has actually been
     * disbursed (Epic 4's PayoutService) — not just approved for release.
     */
    public function markCompleted(Transaction $transaction, User $admin, ?string $note = null): void
    {
        abort_unless($transaction->escrow_status === EscrowStatus::PayoutRelease, 409, 'Transaksi belum berada di tahap payout release.');
        abort_unless($transaction->payout_status === TransactionPayoutStatus::Paid, 409, 'Dana belum dicairkan ke penjual (payout belum berhasil).');

        $transaction->update(['escrow_status' => EscrowStatus::Selesai]);

        $this->recordHistory($transaction, EscrowStatus::PayoutRelease, EscrowStatus::Selesai, $admin, $note);
    }

    /**
     * Buyer or seller reports a mismatch (dispute) while funds are still
     * held by the platform. Remembers the state to resume to if the admin
     * later rejects the dispute.
     */
    public function openDispute(Transaction $transaction, User $actor, string $reason): void
    {
        abort_unless($actor->id === $transaction->buyer_id || $actor->id === $transaction->seller_id, 403, 'Anda bukan pihak dalam transaksi ini.');
        abort_unless(
            in_array($transaction->escrow_status, [EscrowStatus::EscrowHold, EscrowStatus::SerahTerima], true),
            409,
            'Dispute hanya bisa diajukan selama dana masih ditahan platform.'
        );

        $previous = $transaction->escrow_status;

        $transaction->update([
            'escrow_status' => EscrowStatus::Dispute,
            'escrow_status_before_dispute' => $previous->value,
            'disputed_by' => $actor->id,
            'disputed_at' => now(),
            'dispute_reason' => $reason,
        ]);

        $this->recordHistory($transaction, $previous, EscrowStatus::Dispute, $actor, $reason);
    }

    /**
     * Admin resolves a dispute: either refund the buyer (terminal state,
     * vehicle goes back on the market) or resume the flow from wherever it
     * was interrupted.
     */
    public function resolveDispute(Transaction $transaction, User $admin, string $resolution, ?string $note = null): void
    {
        abort_unless($transaction->escrow_status === EscrowStatus::Dispute, 409, 'Transaksi ini tidak sedang dalam status sengketa.');

        if ($resolution === 'refund') {
            $transaction->update([
                'escrow_status' => EscrowStatus::Refunded,
                'escrow_status_before_dispute' => null,
                'dispute_resolution_note' => $note,
                'dispute_resolved_by' => $admin->id,
                'dispute_resolved_at' => now(),
            ]);
            $transaction->vehicle()->update(['status' => VehicleStatus::Approved]);

            $this->recordHistory($transaction, EscrowStatus::Dispute, EscrowStatus::Refunded, $admin, $note);

            return;
        }

        $target = EscrowStatus::from($transaction->escrow_status_before_dispute);

        $transaction->update([
            'escrow_status' => $target,
            'escrow_status_before_dispute' => null,
            'dispute_resolution_note' => $note,
            'dispute_resolved_by' => $admin->id,
            'dispute_resolved_at' => now(),
        ]);

        $this->recordHistory($transaction, EscrowStatus::Dispute, $target, $admin, $note);
    }

    private function recordHistory(Transaction $transaction, ?EscrowStatus $from, EscrowStatus $to, ?User $actor, ?string $note): void
    {
        TransactionStatusHistory::create([
            'transaction_id' => $transaction->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_id' => $actor?->id,
            'note' => $note,
        ]);
    }
}
