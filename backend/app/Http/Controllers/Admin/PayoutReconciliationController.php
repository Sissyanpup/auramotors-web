<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionPayoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionPayoutResource;
use App\Models\Transaction;
use App\Models\TransactionPayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Epic 4 backlog item 3: reconciliation report — platform commission vs
 * seller payout, sourced from transaction_payouts (the append-only ledger
 * PayoutService writes), not derived on the fly from transactions.
 */
class PayoutReconciliationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage', Transaction::class);

        $from = $request->date('from');
        $to = $request->date('to');

        $payouts = TransactionPayout::query()
            ->with(['transaction.vehicle', 'transaction.seller', 'initiatedBy'])
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(20);

        $paidTotals = TransactionPayout::query()
            ->where('status', TransactionPayoutStatus::Paid)
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->selectRaw('count(*) as paid_count, coalesce(sum(commission_amount), 0) as total_commission, coalesce(sum(payout_amount), 0) as total_payout')
            ->first();

        return TransactionPayoutResource::collection($payouts)->additional([
            'summary' => [
                'paid_count' => (int) $paidTotals->paid_count,
                'total_commission' => number_format((float) $paidTotals->total_commission, 2, '.', ''),
                'total_payout' => number_format((float) $paidTotals->total_payout, 2, '.', ''),
                'total_disbursed' => number_format((float) $paidTotals->total_commission + (float) $paidTotals->total_payout, 2, '.', ''),
            ],
        ])->response();
    }
}
