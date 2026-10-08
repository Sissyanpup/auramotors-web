<?php

namespace App\Payments;

use App\Enums\TransactionPaymentStatus;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * Simulates an invoice entirely inside the app — no outbound network call.
 * Used as the default driver so the app works in a fully offline classroom
 * demo. The buyer "pays" by visiting the frontend mock-payment page, which
 * calls MockPaymentController to flip the transaction's own status directly.
 */
class MockGateway implements PaymentGateway
{
    public function createInvoice(Transaction $transaction): GatewayInvoice
    {
        $reference = (string) Str::uuid();
        $expiresAt = Carbon::now()->addDay()->toImmutable();

        return new GatewayInvoice(
            reference: $reference,
            url: $this->buildCheckoutUrl($reference),
            status: TransactionPaymentStatus::Pending,
            expiresAt: $expiresAt,
        );
    }

    /**
     * `config('app.frontend_url')` bisa berisi CSV multi-origin (dipakai juga
     * oleh CORS/Sanctum). Pilih origin yang host-nya cocok dengan Host header
     * request saat ini — kalau buyer akses backend via `192.168.x`, invoice
     * juga mesti mengarah ke frontend `192.168.x` bukan `localhost`.
     * Fallback ke origin pertama kalau tidak ada yang cocok.
     */
    private function buildCheckoutUrl(string $reference): string
    {
        $frontend = (string) config('app.frontend_url');
        $origins = array_values(array_filter(array_map('trim', explode(',', $frontend))));

        $requestHost = Request::getHost();
        $preferred = null;
        foreach ($origins as $origin) {
            if (parse_url($origin, PHP_URL_HOST) === $requestHost) {
                $preferred = $origin;
                break;
            }
        }

        $chosen = $preferred ?? ($origins[0] ?? 'http://localhost:3000');

        return rtrim($chosen, '/')."/checkout/mock/{$reference}";
    }

    public function fetchStatus(Transaction $transaction): TransactionPaymentStatus
    {
        return $transaction->payment_status;
    }
}
