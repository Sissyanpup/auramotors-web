<?php

namespace App\Payments;

use App\Enums\TransactionPaymentStatus;
use App\Models\Transaction;

interface PaymentGateway
{
    /**
     * Create a payable invoice for the given transaction.
     */
    public function createInvoice(Transaction $transaction): GatewayInvoice;

    /**
     * Fetch the current payment status from the gateway (used for polling,
     * as a fallback when the gateway can't reach us via webhook — e.g. no
     * public URL in a fully offline environment).
     */
    public function fetchStatus(Transaction $transaction): TransactionPaymentStatus;
}
