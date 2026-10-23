<?php

namespace App\Http\Controllers\Buyer;

use App\Credit\CreditService;
use App\Http\Controllers\Controller;
use App\Http\Requests\MockPaymentRequest;
use App\Http\Resources\LoanInstallmentResource;
use App\Http\Resources\VehicleLoanResource;
use App\Models\LoanInstallment;
use App\Models\VehicleLoan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pembayaran angsuran kredit oleh pembeli lewat portal bank simulasi
 * (/bank-portal/{reference}?installment={id}) — angsuran dibayar ke leasing.
 */
class InstallmentController extends Controller
{
    public function show(Request $request, LoanInstallment $installment, CreditService $credit): JsonResponse
    {
        $loan = $this->loanOwnedBy($request, $installment);
        $loan->load(['installments', 'transaction.vehicle']);
        $installment->setRelation('loan', $loan);

        return response()->json([
            'data' => [
                'installment' => new LoanInstallmentResource($installment),
                'loan' => new VehicleLoanResource($loan),
                'is_next_payable' => $credit->nextPayable($loan)?->is($installment) ?? false,
            ],
        ]);
    }

    public function pay(MockPaymentRequest $request, LoanInstallment $installment, CreditService $credit): LoanInstallmentResource
    {
        $this->loanOwnedBy($request, $installment);

        $credit->pay($installment, $request->paymentDetails());

        return new LoanInstallmentResource($installment->fresh('loan'));
    }

    private function loanOwnedBy(Request $request, LoanInstallment $installment): VehicleLoan
    {
        $loan = $installment->loan()->with('transaction')->firstOrFail();
        abort_unless($loan->transaction->buyer_id === $request->user()->id, 403, 'Angsuran ini bukan milik Anda.');

        return $loan;
    }
}
