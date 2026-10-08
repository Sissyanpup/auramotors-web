<?php

namespace App\Payments;

use App\Enums\TransactionPaymentStatus;
use App\Models\Transaction;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the real Xendit sandbox "Invoices" API. Requires internet access —
 * not usable in the fully offline classroom environment, see MockGateway.
 */
class XenditGateway implements PaymentGateway
{
    public function createInvoice(Transaction $transaction): GatewayInvoice
    {
        $response = $this->client()->post('/v2/invoices', [
            'external_id' => "transaction-{$transaction->id}",
            'amount' => (float) $transaction->amount,
            'currency' => 'IDR',
            'payer_email' => $transaction->buyer->email,
            'description' => "DP {$transaction->vehicle->brand} {$transaction->vehicle->model} (transaksi #{$transaction->id})",
            'invoice_duration' => 86400,
        ])->throw()->json();

        return new GatewayInvoice(
            reference: $response['id'],
            url: $response['invoice_url'],
            status: TransactionPaymentStatus::fromXenditStatus($response['status']),
            expiresAt: isset($response['expiry_date']) ? Carbon::parse($response['expiry_date'])->toImmutable() : null,
        );
    }

    public function fetchStatus(Transaction $transaction): TransactionPaymentStatus
    {
        if (! $transaction->gateway_reference) {
            throw new RuntimeException('Transaksi belum punya referensi invoice Xendit.');
        }

        $response = $this->client()->get("/v2/invoices/{$transaction->gateway_reference}")->throw()->json();

        return TransactionPaymentStatus::fromXenditStatus($response['status']);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('payment.xendit.base_url'))
            ->withBasicAuth(config('payment.xendit.secret_key'), '')
            ->acceptJson();
    }
}
