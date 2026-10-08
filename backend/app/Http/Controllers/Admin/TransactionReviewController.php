<?php

namespace App\Http\Controllers\Admin;

use App\Documents\DocumentGenerator;
use App\Enums\TransactionPaymentStatus;
use App\Escrow\EscrowStateMachine;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DisburseTransactionRequest;
use App\Http\Requests\Admin\EscrowTransitionRequest;
use App\Http\Requests\Admin\ResolveDisputeRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Payouts\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionReviewController extends Controller
{
    private const DETAIL_RELATIONS = ['vehicle.photos', 'buyer', 'seller.sellerProfile', 'statusHistories.actor', 'payouts', 'documents'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage', Transaction::class);

        $escrowStatus = $request->query('escrow_status');

        $transactions = Transaction::query()
            ->with(['vehicle.photos', 'buyer:id,name', 'seller:id,name'])
            ->where('payment_status', TransactionPaymentStatus::Paid)
            ->when($escrowStatus, fn ($query) => $query->where('escrow_status', $escrowStatus))
            ->latest()
            ->paginate(20);

        return TransactionResource::collection($transactions);
    }

    public function show(Transaction $transaction): TransactionResource
    {
        $this->authorize('manage', Transaction::class);

        return new TransactionResource($transaction->load(self::DETAIL_RELATIONS));
    }

    public function approveHandover(EscrowTransitionRequest $request, Transaction $transaction, EscrowStateMachine $escrow, DocumentGenerator $docs): TransactionResource
    {
        $escrow->approveHandover($transaction, $request->user(), $request->validated('note'));

        // Bill of Sale auto-generated saat admin menyetujui serah-terima (Epic 7):
        // kedua belah pihak sudah konfirmasi, jadi objek & waktu serah-terima definitif.
        $docs->generateBillOfSale($transaction->fresh());

        return new TransactionResource($transaction->fresh(self::DETAIL_RELATIONS));
    }

    public function approvePayout(EscrowTransitionRequest $request, Transaction $transaction, EscrowStateMachine $escrow): TransactionResource
    {
        $escrow->approvePayoutRelease($transaction, $request->user(), $request->validated('note'));

        return new TransactionResource($transaction->fresh(self::DETAIL_RELATIONS));
    }

    public function disburse(DisburseTransactionRequest $request, Transaction $transaction, PayoutService $payouts, DocumentGenerator $docs): TransactionResource
    {
        $this->authorize('manage', Transaction::class);

        $payouts->disburse($transaction, $request->user(), $request->validated('reference'), $request->validated('note'));

        // Escrow Disbursement Note auto-generated setelah pencairan berhasil (payout status = paid).
        // Kalau gagal, PayoutService melempar exception dan baris ini tidak tereksekusi.
        $docs->generateEscrowDisbursementNote($transaction->fresh(['payouts', 'seller.sellerProfile']));

        return new TransactionResource($transaction->fresh(self::DETAIL_RELATIONS));
    }

    public function markCompleted(EscrowTransitionRequest $request, Transaction $transaction, EscrowStateMachine $escrow): TransactionResource
    {
        $escrow->markCompleted($transaction, $request->user(), $request->validated('note'));

        return new TransactionResource($transaction->fresh(self::DETAIL_RELATIONS));
    }

    public function resolveDispute(ResolveDisputeRequest $request, Transaction $transaction, EscrowStateMachine $escrow): TransactionResource
    {
        $escrow->resolveDispute($transaction, $request->user(), $request->validated('resolution'), $request->validated('note'));

        return new TransactionResource($transaction->fresh(self::DETAIL_RELATIONS));
    }
}
