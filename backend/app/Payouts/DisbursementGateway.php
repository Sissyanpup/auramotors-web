<?php

namespace App\Payouts;

use App\Models\TransactionPayout;

interface DisbursementGateway
{
    /**
     * Send the held funds (minus platform commission) to the seller for the
     * given payout attempt. $payout already has commission/payout amounts
     * and the admin-supplied reference (if any) set, but is not yet saved
     * as paid/failed — that's this call's job to determine.
     */
    public function disburse(TransactionPayout $payout): PayoutResult;
}
