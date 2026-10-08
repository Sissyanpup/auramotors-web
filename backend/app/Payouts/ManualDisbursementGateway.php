<?php

namespace App\Payouts;

use App\Enums\TransactionPayoutStatus;
use App\Models\TransactionPayout;

/**
 * Records a payout the admin already sent by hand (bank transfer outside
 * the app) — no outbound network call, works in the fully offline
 * classroom environment. The admin-supplied transfer reference is the
 * proof of payment; this driver just confirms it was provided.
 */
class ManualDisbursementGateway implements DisbursementGateway
{
    public function disburse(TransactionPayout $payout): PayoutResult
    {
        abort_if(blank($payout->reference), 422, 'Nomor referensi transfer wajib diisi untuk payout manual.');

        return new PayoutResult(status: TransactionPayoutStatus::Paid, reference: $payout->reference);
    }
}
