<?php

namespace App\Http\Controllers;

use App\Credit\CreditService;
use App\Documents\DocumentGenerator;
use App\Escrow\EscrowStateMachine;
use App\Http\Requests\TransactionDisputeRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Shared tracking endpoints for both parties of a transaction (buyer &
 * seller) — see routes/api.php for how the same controller is mounted
 * under both /buyer and /seller.
 */
class TransactionController extends Controller
{
    private const DETAIL_RELATIONS = ['vehicle.photos', 'statusHistories.actor', 'documents', 'loan.installments'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Transaction::class);

        $query = $request->user()->isSeller()
            ? $request->user()->salesTransactions()
            : $request->user()->transactions();

        $transactions = $query->with('vehicle.coverPhoto')->latest()->paginate(20);

        return TransactionResource::collection($transactions);
    }

    public function show(Transaction $transaction, CreditService $credit): TransactionResource
    {
        $this->authorize('view', $transaction);

        $credit->syncAutoApproval($transaction->loan);

        return new TransactionResource($transaction->load(self::DETAIL_RELATIONS));
    }

    public function requestHandover(Request $request, Transaction $transaction, EscrowStateMachine $escrow): TransactionResource
    {
        $this->authorize('view', $transaction);

        $escrow->requestHandover($transaction, $request->user());

        return new TransactionResource($transaction->fresh(self::DETAIL_RELATIONS));
    }

    public function confirmHandover(Request $request, Transaction $transaction, EscrowStateMachine $escrow, DocumentGenerator $docs): TransactionResource
    {
        $this->authorize('view', $transaction);

        $escrow->confirmHandover($transaction, $request->user());

        // Bill of Sale terbit saat pembeli menerima kendaraan: objek & waktu serah terima
        // baru definitif setelah penjual menyerahkan dan pembeli mengonfirmasi.
        $fresh = $transaction->fresh();
        if ($fresh->buyer_confirmed_at && $fresh->seller_confirmed_at) {
            $docs->generateBillOfSale($fresh);
        }

        return new TransactionResource($transaction->fresh(self::DETAIL_RELATIONS));
    }

    public function dispute(TransactionDisputeRequest $request, Transaction $transaction, EscrowStateMachine $escrow): TransactionResource
    {
        $escrow->openDispute($transaction, $request->user(), $request->validated('reason'));

        return new TransactionResource($transaction->fresh(self::DETAIL_RELATIONS));
    }
}
