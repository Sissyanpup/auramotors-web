<?php

namespace App\Payouts;

use App\Enums\TransactionPayoutStatus;
use App\Models\TransactionPayout;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the real Xendit "Disbursements" API. Requires internet access —
 * not usable in the fully offline classroom environment, see
 * ManualDisbursementGateway. Never exercised against the live API in class;
 * only covered by tests via Http::fake(), same as XenditGateway.
 */
class XenditDisbursementGateway implements DisbursementGateway
{
    public function disburse(TransactionPayout $payout): PayoutResult
    {
        $transaction = $payout->transaction;
        $profile = $transaction->seller->sellerProfile;

        abort_if(
            blank($profile?->bank_name) || blank($profile?->bank_account_number) || blank($profile?->bank_account_holder_name),
            422,
            'Penjual belum melengkapi data rekening bank untuk menerima payout.'
        );

        $response = $this->client()->post('/disbursements', [
            'external_id' => "payout-{$payout->transaction_id}-{$payout->id}",
            'amount' => (float) $payout->payout_amount,
            'bank_code' => $profile->bank_name,
            'account_holder_name' => $profile->bank_account_holder_name,
            'account_number' => $profile->bank_account_number,
            'description' => "Payout transaksi #{$payout->transaction_id}",
        ])->throw()->json();

        return new PayoutResult(
            status: match (strtoupper($response['status'] ?? '')) {
                'COMPLETED' => TransactionPayoutStatus::Paid,
                'FAILED' => TransactionPayoutStatus::Failed,
                default => TransactionPayoutStatus::Pending,
            },
            reference: $response['id'] ?? null,
            failureReason: $response['failure_code'] ?? null,
        );
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('payout.xendit.base_url'))
            ->withBasicAuth(config('payout.xendit.secret_key'), '')
            ->acceptJson();
    }
}
