<?php

namespace App\Http\Controllers\Buyer;

use App\Documents\DocumentGenerator;
use App\Enums\BuyerProfileStatus;
use App\Enums\PaymentGatewayDriver;
use App\Enums\TransactionPaymentStatus;
use App\Escrow\EscrowStateMachine;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\CancelTransactionRequest;
use App\Http\Requests\Buyer\CheckoutRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Payments\PaymentGateway;
use App\Support\InvoiceNumber;
use App\Support\PriceBreakdownCalculator;

class CheckoutController extends Controller
{
    public function store(CheckoutRequest $request, Vehicle $vehicle, PaymentGateway $gateway, DocumentGenerator $docs, PriceBreakdownCalculator $pricing): TransactionResource
    {
        $this->authorize('checkout', $vehicle);

        abort_if($this->hasActiveTransaction($vehicle), 409, 'Kendaraan ini sudah punya transaksi berjalan atau sudah lunas.');

        $this->guardHighValueKyc($request->user(), (float) $vehicle->price);

        $scheme = $request->scheme();
        $insuranceType = $request->insuranceType();
        $breakdown = $pricing->forVehicle($vehicle, $scheme, (float) $request->validated('dp_percent'), $insuranceType, $request->insuranceAddons());

        $escrowBank = config('payment.escrow_bank');

        $transaction = Transaction::create([
            'vehicle_id' => $vehicle->id,
            'buyer_id' => $request->user()->id,
            'seller_id' => $vehicle->seller_id,
            'amount' => $breakdown['due_now']['total'],
            'payment_scheme' => $scheme,
            'dp_percent' => $breakdown['payment']['dp_percent'],
            'vehicle_price' => $breakdown['vehicle_price'],
            'insurance_type' => $insuranceType,
            'insurance_premium' => $breakdown['insurance']['total'],
            'insurance_addons' => $breakdown['insurance']['addons'],
            'price_breakdown' => $breakdown,
            'buyer_address' => $request->validated('buyer_address'),
            'buyer_phone' => $request->validated('buyer_phone'),
            'buyer_notes' => $request->validated('buyer_notes'),
            'bank_transfer_bank' => $escrowBank['bank'] ?? null,
            'bank_transfer_account_number' => $escrowBank['account_number'] ?? null,
            'bank_transfer_account_holder' => $escrowBank['account_holder'] ?? null,
            'payment_gateway' => PaymentGatewayDriver::from(config('payment.gateway')),
            'payment_status' => TransactionPaymentStatus::Pending,
        ]);

        // Assign invoice_number setelah punya id agar sequence numerik konsisten.
        $transaction->update(['invoice_number' => InvoiceNumber::generate($transaction)]);

        $invoice = $gateway->createInvoice($transaction);

        $transaction->update([
            'gateway_reference' => $invoice->reference,
            'gateway_invoice_url' => $invoice->url,
            'expires_at' => $invoice->expiresAt,
        ]);

        // Auto-generated saat checkout selesai (Epic 7 + Sprint 10): invoice, SPA, rincian harga, SPPA.
        $docs->generateCheckoutDocuments($transaction);

        return new TransactionResource($transaction->load('vehicle.photos'));
    }

    public function refreshStatus(Transaction $transaction, PaymentGateway $gateway, EscrowStateMachine $escrow, DocumentGenerator $docs): TransactionResource
    {
        $this->authorize('view', $transaction);

        if ($transaction->payment_status === TransactionPaymentStatus::Pending) {
            $status = $gateway->fetchStatus($transaction);

            $transaction->update([
                'payment_status' => $status,
                'paid_at' => $status === TransactionPaymentStatus::Paid ? now() : null,
            ]);

            if ($status === TransactionPaymentStatus::Paid) {
                $escrow->holdFunds($transaction);
                $docs->generatePaidDocuments($transaction);
            }
        }

        return new TransactionResource($transaction->load('vehicle.photos'));
    }

    /**
     * Buyer membatalkan transaksi yang belum dibayar. Alasan wajib diisi
     * agar seller/admin tahu konteks pembatalan lewat riwayat status.
     * Setelah dibatalkan, kendaraan otomatis kembali listable karena
     * hasActiveTransaction() tidak lagi menghitung transaksi ini (statusnya
     * bukan pending/paid).
     */
    public function cancel(CancelTransactionRequest $request, Transaction $transaction): TransactionResource
    {
        abort_unless(
            $transaction->payment_status === TransactionPaymentStatus::Pending,
            409,
            'Hanya transaksi yang belum dibayar (pending) yang bisa dibatalkan.'
        );

        $transaction->update([
            'payment_status' => TransactionPaymentStatus::Cancelled,
            'cancellation_reason' => $request->validated('reason'),
            'cancelled_by' => $request->user()->id,
            'cancelled_at' => now(),
        ]);

        return new TransactionResource($transaction->fresh(['vehicle.photos']));
    }

    private function hasActiveTransaction(Vehicle $vehicle): bool
    {
        return $vehicle->transactions()
            ->where(function ($query) {
                $query->where('payment_status', TransactionPaymentStatus::Paid)
                    ->orWhere(function ($pending) {
                        $pending->where('payment_status', TransactionPaymentStatus::Pending)
                            ->where(function ($notExpired) {
                                $notExpired->whereNull('expires_at')->orWhere('expires_at', '>', now());
                            });
                    });
            })
            ->exists();
    }

    /**
     * Untuk transaksi bernilai tinggi (di atas `config('kyc.high_value_threshold')`),
     * buyer wajib menyelesaikan KYC + Proof of Funds sebelum checkout. Sesuai standar
     * AML untuk transaksi kendaraan mewah (Epic 6).
     */
    private function guardHighValueKyc(User $buyer, float $vehiclePrice): void
    {
        $threshold = (float) config('kyc.high_value_threshold');

        if ($vehiclePrice < $threshold) {
            return;
        }

        $profile = $buyer->buyerProfile;

        abort_if(
            $profile === null || $profile->status !== BuyerProfileStatus::Approved,
            403,
            'Transaksi bernilai tinggi (di atas Rp'.number_format($threshold, 0, ',', '.').') mensyaratkan KYC & Proof of Funds yang sudah diverifikasi admin. Lengkapi di /buyer/kyc terlebih dahulu.'
        );
    }
}
