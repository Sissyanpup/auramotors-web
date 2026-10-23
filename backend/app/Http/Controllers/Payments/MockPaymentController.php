<?php

namespace App\Http\Controllers\Payments;

use App\Credit\CreditService;
use App\Documents\DocumentGenerator;
use App\Enums\PaymentGatewayDriver;
use App\Enums\TransactionPaymentStatus;
use App\Escrow\EscrowStateMachine;
use App\Http\Controllers\Controller;
use App\Http\Requests\MockPaymentRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Support\Str;

/**
 * Simulates the buyer-facing invoice page for the offline "mock" gateway —
 * stands in for the real Xendit checkout page when there is no internet
 * access (see App\Payments\MockGateway).
 */
class MockPaymentController extends Controller
{
    public function show(string $reference): TransactionResource
    {
        return new TransactionResource($this->findTransaction($reference)->load(['vehicle.photos', 'seller', 'documents']));
    }

    /**
     * Dipanggil portal bank simulasi setelah buyer lolos form kartu/PayPal/VA
     * + OTP. Detail metode bayar (tanpa nomor kartu penuh/CVV) dicatat untuk
     * Bukti Pembayaran Bank, lalu dana masuk escrow & dokumen pasca-bayar terbit.
     */
    public function pay(MockPaymentRequest $request, string $reference, EscrowStateMachine $escrow, DocumentGenerator $docs, CreditService $credit): TransactionResource
    {
        $transaction = $this->findTransaction($reference);

        abort_unless($transaction->payment_status === TransactionPaymentStatus::Pending, 409, 'Transaksi ini sudah tidak berstatus pending.');

        $transaction->update([
            ...$request->paymentDetails(),
            'payment_approval_code' => Str::upper(Str::random(6)),
            'payment_status' => TransactionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $escrow->holdFunds($transaction);
        $docs->generatePaidDocuments($transaction);
        $credit->openApplication($transaction);

        return new TransactionResource($transaction->fresh(['vehicle.photos', 'seller', 'documents']));
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
