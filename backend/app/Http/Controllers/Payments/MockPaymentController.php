<?php

namespace App\Http\Controllers\Payments;

use App\Documents\DocumentGenerator;
use App\Enums\PaymentGatewayDriver;
use App\Enums\TransactionPaymentStatus;
use App\Escrow\EscrowStateMachine;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;

/**
 * Simulates the buyer-facing invoice page for the offline "mock" gateway —
 * stands in for the real Xendit checkout page when there is no internet
 * access (see App\Payments\MockGateway).
 */
class MockPaymentController extends Controller
{
    public function show(string $reference): TransactionResource
    {
        return new TransactionResource($this->findTransaction($reference)->load('vehicle.photos'));
    }

    public function pay(string $reference, EscrowStateMachine $escrow, DocumentGenerator $docs): TransactionResource
    {
        $transaction = $this->findTransaction($reference);

        abort_unless($transaction->payment_status === TransactionPaymentStatus::Pending, 409, 'Transaksi ini sudah tidak berstatus pending.');

        $transaction->update([
            'payment_status' => TransactionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $escrow->holdFunds($transaction);
        $docs->generateDepositReceipt($transaction);
        $docs->generateTaxInvoice($transaction);

        return new TransactionResource($transaction->load('vehicle.photos'));
    }

    public function fail(string $reference): TransactionResource
    {
        $transaction = $this->findTransaction($reference);

        abort_unless($transaction->payment_status === TransactionPaymentStatus::Pending, 409, 'Transaksi ini sudah tidak berstatus pending.');

        $transaction->update(['payment_status' => TransactionPaymentStatus::Failed]);

        return new TransactionResource($transaction->load('vehicle.photos'));
    }

    private function findTransaction(string $reference): Transaction
    {
        $transaction = Transaction::where('gateway_reference', $reference)
            ->where('payment_gateway', PaymentGatewayDriver::Mock)
            ->firstOrFail();

        $this->authorize('view', $transaction);

        return $transaction;
    }
}
