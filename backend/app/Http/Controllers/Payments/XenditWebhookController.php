<?php

namespace App\Http\Controllers\Payments;

use App\Documents\DocumentGenerator;
use App\Enums\TransactionPaymentStatus;
use App\Escrow\EscrowStateMachine;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives Xendit's invoice callback. Only reachable when the app is exposed
 * on a public URL (e.g. via a tunnel during a demo) — the offline classroom
 * flow relies on CheckoutController::refreshStatus polling instead.
 */
class XenditWebhookController extends Controller
{
    public function __invoke(Request $request, EscrowStateMachine $escrow, DocumentGenerator $docs): Response
    {
        abort_unless(
            config('payment.xendit.callback_verification_token') !== null
                && hash_equals((string) config('payment.xendit.callback_verification_token'), (string) $request->header('x-callback-token')),
            403,
            'Token verifikasi callback tidak valid.'
        );

        $transaction = Transaction::where('gateway_reference', (string) $request->string('id'))->first();

        if (! $transaction) {
            return response()->noContent();
        }

        $status = TransactionPaymentStatus::fromXenditStatus((string) $request->string('status'));

        $transaction->update([
            'payment_status' => $status,
            'paid_at' => $status === TransactionPaymentStatus::Paid ? now() : $transaction->paid_at,
        ]);

        if ($status === TransactionPaymentStatus::Paid) {
            $escrow->holdFunds($transaction);
            $docs->generateDepositReceipt($transaction);
            $docs->generateTaxInvoice($transaction);
        }

        return response()->noContent();
    }
}
