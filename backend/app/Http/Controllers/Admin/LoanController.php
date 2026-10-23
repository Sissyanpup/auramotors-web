<?php

namespace App\Http\Controllers\Admin;

use App\Credit\CreditService;
use App\Enums\LoanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EscrowTransitionRequest;
use App\Http\Requests\Admin\RecordInstallmentPaymentRequest;
use App\Http\Resources\VehicleLoanResource;
use App\Models\LoanInstallment;
use App\Models\Transaction;
use App\Models\VehicleLoan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kelola kredit & cicilan (revisi dosen 16 Okt): pantau semua pinjaman
 * leasing, setujui pengajuan lebih cepat, catat setoran manual, dan
 * majukan jam simulasi untuk demo jatuh tempo & denda.
 */
class LoanController extends Controller
{
    private const RELATIONS = ['transaction.vehicle', 'transaction.buyer', 'transaction.seller', 'installments.recordedBy', 'approvedBy'];

    public function index(Request $request, CreditService $credit): JsonResponse
    {
        $this->authorize('manage', Transaction::class);

        VehicleLoan::query()
            ->where('status', LoanStatus::Submitted)
            ->where('auto_approve_at', '<=', now())
            ->get()
            ->each(fn (VehicleLoan $loan) => $credit->syncAutoApproval($loan));

        $loans = VehicleLoan::query()->with(self::RELATIONS)->latest()->get();

        $summaries = $loans->mapWithKeys(fn (VehicleLoan $loan) => [$loan->id => $credit->summary($loan)]);

        $filter = $request->query('filter');
        $filtered = $loans->filter(fn (VehicleLoan $loan) => match ($filter) {
            'submitted' => $loan->status === LoanStatus::Submitted,
            'active' => $loan->status === LoanStatus::Approved,
            'overdue' => $summaries[$loan->id]['overdue_count'] > 0,
            'paid_off' => $loan->status === LoanStatus::PaidOff,
            default => true,
        })->values();

        $active = $loans->where('status', LoanStatus::Approved);

        return response()->json([
            'data' => VehicleLoanResource::collection($filtered),
            'stats' => [
                'total' => $loans->count(),
                'submitted' => $loans->where('status', LoanStatus::Submitted)->count(),
                'active' => $active->count(),
                'paid_off' => $loans->where('status', LoanStatus::PaidOff)->count(),
                'overdue_loans' => $summaries->filter(fn (array $summary) => $summary['overdue_count'] > 0)->count(),
                'outstanding_principal' => (float) $active->sum(fn (VehicleLoan $loan) => $summaries[$loan->id]['outstanding_principal']),
                'late_fees_due' => (float) $summaries->sum('late_fees_due'),
                'collected' => (float) $loans->flatMap->installments->whereNotNull('paid_at')->sum('paid_amount'),
            ],
        ]);
    }

    public function show(VehicleLoan $loan, CreditService $credit): VehicleLoanResource
    {
        $this->authorize('manage', Transaction::class);

        $credit->syncAutoApproval($loan);

        return new VehicleLoanResource($loan->fresh(self::RELATIONS));
    }

    public function approve(EscrowTransitionRequest $request, VehicleLoan $loan, CreditService $credit): VehicleLoanResource
    {
        $credit->approve($loan, $request->user());

        return new VehicleLoanResource($loan->fresh(self::RELATIONS));
    }

    public function advanceClock(EscrowTransitionRequest $request, VehicleLoan $loan, CreditService $credit): VehicleLoanResource
    {
        $credit->advanceClock($loan);

        return new VehicleLoanResource($loan->fresh(self::RELATIONS));
    }

    public function recordPayment(RecordInstallmentPaymentRequest $request, LoanInstallment $installment, CreditService $credit): VehicleLoanResource
    {
        $credit->pay(
            $installment,
            ['payment_method' => 'manual', 'payment_channel' => $request->validated('channel'), 'payment_account_masked' => null],
            $request->user(),
            $request->validated('note'),
        );

        return new VehicleLoanResource($installment->loan->fresh(self::RELATIONS));
    }
}
