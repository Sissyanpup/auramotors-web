<?php

namespace App\Http\Controllers;

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
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Transaction::class);

        $query = $request->user()->isSeller()
            ? $request->user()->salesTransactions()
            : $request->user()->transactions();

        $transactions = $query->with('vehicle.coverPhoto')->latest()->paginate(20);

        return TransactionResource::collection($transactions);
    }

    public function show(Transaction $transaction): TransactionResource
    {
        $this->authorize('view', $transaction);

        return new TransactionResource($transaction->load(['vehicle.photos', 'statusHistories.actor', 'documents']));
    }

    public function confirmHandover(Request $request, Transaction $transaction, EscrowStateMachine $escrow): TransactionResource
    {
        $this->authorize('view', $transaction);

        $escrow->confirmHandover($transaction, $request->user());

        return new TransactionResource($transaction->fresh(['vehicle.photos', 'statusHistories.actor', 'documents']));
    }

    public function dispute(TransactionDisputeRequest $request, Transaction $transaction, EscrowStateMachine $escrow): TransactionResource
    {
        $escrow->openDispute($transaction, $request->user(), $request->validated('reason'));

        return new TransactionResource($transaction->fresh(['vehicle.photos', 'statusHistories.actor', 'documents']));
    }
}
