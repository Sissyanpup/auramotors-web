<?php

namespace App\Escrow;

use App\Enums\EscrowStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentScheme;
use App\Enums\TransactionDocumentType;
use App\Enums\TransactionPaymentStatus;
use App\Enums\TransactionPayoutStatus;
use App\Enums\VehicleStatus;
use App\Models\Transaction;
use App\Models\TransactionDocumentSignature;
use App\Models\TransactionStatusHistory;
use App\Models\User;

/**
 * Drives the escrow state machine documented in docs/03-payment-escrow.md:
 * escrow_hold -> serah_terima -> payout_release -> selesai, with an optional
 * dispute branch back to either a resumed state or a refund.
 *
 * Serah terima is approval-first: the seller requests it, the admin checks
 * the checklist and approves (escrow_hold -> serah_terima), and only then
 * does the seller hand over / dispatch and the buyer confirm receipt.
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
     * Seller asks the admin for permission to hand over / dispatch the
     * vehicle. Nothing may leave the seller's hands before that approval.
     */
    public function requestHandover(Transaction $transaction, User $seller): void
    {
        abort_unless($seller->id === $transaction->seller_id, 403, 'Hanya penjual yang dapat mengajukan serah terima.');
        abort_unless($transaction->escrow_status === EscrowStatus::EscrowHold, 409, 'Transaksi belum berada di tahap penahanan dana (escrow).');
        abort_if($transaction->handover_requested_at !== null, 409, 'Pengajuan serah terima sudah dikirim dan sedang menunggu persetujuan admin.');

        $transaction->update(['handover_requested_at' => now()]);
    }

    /**
     * Admin approves the escrow_hold -> serah_terima transition. From this
     * point the seller is allowed to hand over or dispatch the vehicle.
     */
    public function approveHandover(Transaction $transaction, User $admin, ?string $note = null): void
    {
        abort_unless($transaction->escrow_status === EscrowStatus::EscrowHold, 409, 'Transaksi tidak berada di tahap penahanan dana.');

        $unmet = collect($this->handoverChecklist($transaction))->reject(fn (array $item) => $item['is_met']);
        abort_if($unmet->isNotEmpty(), 422, 'Syarat serah terima belum terpenuhi: '.$unmet->pluck('label')->implode('; ').'.');

        $transaction->update([
            'escrow_status' => EscrowStatus::SerahTerima,
            'handover_approved_at' => now(),
            'handover_approved_by' => $admin->id,
        ]);

        $this->recordHistory($transaction, EscrowStatus::EscrowHold, EscrowStatus::SerahTerima, $admin, $note ?? 'Serah terima disetujui — penjual boleh menyerahkan/memberangkatkan kendaraan.');
    }

    /**
     * Syarat yang diperiksa admin sebelum mengizinkan serah terima. Dokumen
     * yang belum pernah terbit (transaksi lama/seed) tidak dijadikan syarat.
     *
     * @return array<int, array{key: string, label: string, is_met: bool}>
     */
    public function handoverChecklist(Transaction $transaction): array
    {
        $transaction->loadMissing(['documents', 'documentSignatures']);

        $items = [
            [
                'key' => 'funds_in_escrow',
                'label' => 'Dana pembeli sudah masuk rekening escrow',
                'is_met' => $transaction->payment_status === TransactionPaymentStatus::Paid && $transaction->escrow_status !== null,
            ],
            [
                'key' => 'handover_requested',
                'label' => 'Penjual sudah mengajukan serah terima',
                'is_met' => $transaction->handover_requested_at !== null,
            ],
        ];

        // Kredit: kendaraan baru boleh diserahkan setelah leasing menyetujui dan
        // melunasi sisa harga ke penjual.
        if ($transaction->payment_scheme === PaymentScheme::Credit) {
            $transaction->loadMissing('loan');
            $items[] = [
                'key' => 'credit_approved',
                'label' => 'Kredit disetujui leasing & sisa harga dilunasi ke penjual',
                'is_met' => in_array($transaction->loan?->status, [LoanStatus::Approved, LoanStatus::PaidOff], true),
            ];
        }

        foreach ([TransactionDocumentType::SalesPurchaseAgreement, TransactionDocumentType::SellerOwnershipStatement] as $type) {
            if (! $transaction->documents->contains('type', $type)) {
                continue;
            }

            $items[] = [
                'key' => 'signed_'.$type->value,
                'label' => $type->label().' ditandatangani '.$this->signerLabel($type),
                'is_met' => $this->isFullySigned($transaction, $type),
            ];
        }

        return $items;
    }

    /**
     * After the admin's approval: the seller confirms the vehicle has been
     * handed over / dispatched, then the buyer confirms receiving it.
     */
    public function confirmHandover(Transaction $transaction, User $actor): void
    {
        abort_unless($transaction->escrow_status === EscrowStatus::SerahTerima, 409, 'Serah terima belum disetujui admin — kendaraan belum boleh diserahkan.');

        if ($actor->id === $transaction->seller_id) {
            abort_if($transaction->seller_confirmed_at !== null, 409, 'Anda sudah mengonfirmasi penyerahan kendaraan sebelumnya.');
            $transaction->update(['seller_confirmed_at' => now()]);

            return;
        }

        if ($actor->id === $transaction->buyer_id) {
            abort_if($transaction->seller_confirmed_at === null, 409, 'Penjual belum menyerahkan/memberangkatkan kendaraan.');
            abort_if($transaction->buyer_confirmed_at !== null, 409, 'Anda sudah mengonfirmasi penerimaan kendaraan sebelumnya.');
            $transaction->update(['buyer_confirmed_at' => now()]);

            return;
        }

        abort(403, 'Anda bukan pihak dalam transaksi ini.');
    }

    /**
     * Admin approves releasing the held funds to the seller (the actual
     * disbursement is Epic 4's scope — this only records the approval).
     * Requires the handover to be confirmed by both parties and the BAST
     * signed by both.
     */
    public function approvePayoutRelease(Transaction $transaction, User $admin, ?string $note = null): void
    {
        abort_unless($transaction->escrow_status === EscrowStatus::SerahTerima, 409, 'Transaksi belum berada di tahap serah-terima.');
        abort_unless($transaction->seller_confirmed_at && $transaction->buyer_confirmed_at, 422, 'Kendaraan belum dikonfirmasi diserahkan penjual & diterima pembeli.');

        $transaction->loadMissing(['documents', 'documentSignatures']);
        $bast = TransactionDocumentType::HandoverMinutes;
        abort_if(
            $transaction->documents->contains('type', $bast) && ! $this->isFullySigned($transaction, $bast),
            422,
            $bast->label().' belum ditandatangani penjual & pembeli.',
        );

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

    private function isFullySigned(Transaction $transaction, TransactionDocumentType $type): bool
    {
        foreach ($type->requiredSigners() as $role) {
            $signed = $transaction->documentSignatures
                ->contains(fn (TransactionDocumentSignature $signature) => $signature->document_type === $type && $signature->role === $role);

            if (! $signed) {
                return false;
            }
        }

        return true;
    }

    private function signerLabel(TransactionDocumentType $type): string
    {
        return collect($type->requiredSigners())
            ->map(fn (string $role) => $role === 'seller' ? 'penjual' : 'pembeli')
            ->implode(' & ');
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
