<?php

namespace App\Http\Controllers\Admin;

use App\Credit\CreditService;
use App\Enums\BuyerProfileStatus;
use App\Enums\EscrowStatus;
use App\Enums\SellerProfileStatus;
use App\Enums\TransactionPaymentStatus;
use App\Enums\TransactionPayoutStatus;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Models\BuyerProfile;
use App\Models\LoanInstallment;
use App\Models\PromoRedemption;
use App\Models\SellerBonus;
use App\Models\SellerProfile;
use App\Models\Transaction;
use App\Models\TransactionPayout;
use App\Models\Vehicle;
use App\Models\VehicleLoan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan lengkap untuk admin (catatan dosen 16 Okt): ringkasan KPI, deret
 * bulanan untuk grafik, komposisi status, serta tabel rinci penjualan,
 * pencairan, bonus, promo, kredit, dan verifikasi dalam satu rentang tanggal.
 *
 * Agregasi dilakukan di PHP (bukan fungsi tanggal SQL) supaya sama di SQLite
 * dan PostgreSQL/MySQL; volume data proyek kelas kecil.
 */
class ReportController extends Controller
{
    public function index(Request $request, CreditService $credit): JsonResponse
    {
        $this->authorize('manage', Transaction::class);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : now()->endOfDay();
        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : $to->copy()->subMonthsNoOverflow(11)->startOfMonth();

        $paid = Transaction::query()
            ->with(['vehicle:id,brand,model,year', 'buyer:id,name', 'seller:id,name'])
            ->where('payment_status', TransactionPaymentStatus::Paid)
            ->whereBetween('paid_at', [$from, $to])
            ->orderByDesc('paid_at')
            ->get();

        $created = Transaction::query()->whereBetween('created_at', [$from, $to])->get(['id', 'payment_status']);

        $payouts = TransactionPayout::query()
            ->with(['transaction.vehicle:id,brand,model,year', 'transaction.seller:id,name'])
            ->where('status', TransactionPayoutStatus::Paid)
            ->whereBetween('paid_at', [$from, $to])
            ->orderByDesc('paid_at')
            ->get();

        $bonuses = SellerBonus::query()->with('seller:id,name')->whereBetween('created_at', [$from, $to])->latest()->get();
        $redemptions = PromoRedemption::query()->with(['promo:id,name,code,benefit', 'user:id,name'])->whereBetween('created_at', [$from, $to])->latest()->get();
        $loans = VehicleLoan::query()->with(['installments', 'transaction.buyer:id,name', 'transaction.vehicle:id,brand,model,year'])->latest()->get();
        $installmentsPaid = LoanInstallment::query()->whereNotNull('paid_at')->whereBetween('paid_at', [$from, $to])->get(['paid_amount', 'late_fee']);

        $gmv = (float) $paid->sum(fn (Transaction $t) => (float) $t->vehicle_price);
        $loanSummaries = $loans->mapWithKeys(fn (VehicleLoan $loan) => [$loan->id => $credit->summary($loan)]);

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'kpis' => [
                'gmv' => $gmv,
                'transactions_paid' => $paid->count(),
                'transactions_created' => $created->count(),
                'transactions_cancelled' => $created->where('payment_status', TransactionPaymentStatus::Cancelled)->count(),
                'average_ticket' => $paid->count() > 0 ? round($gmv / $paid->count(), 0) : 0,
                'escrow_collected' => (float) $paid->sum('amount'),
                'escrow_held' => (float) Transaction::query()->whereIn('escrow_status', [EscrowStatus::EscrowHold, EscrowStatus::SerahTerima, EscrowStatus::PayoutRelease])->sum('amount'),
                'commission_revenue' => (float) $payouts->sum('commission_amount'),
                'payouts_paid' => (float) $payouts->sum('payout_amount'),
                'bonuses_paid' => (float) $bonuses->sum('amount'),
                'promo_benefit' => (float) $redemptions->sum('benefit_amount'),
                'loans_active' => $loans->where('status.value', 'approved')->count(),
                'loan_outstanding_principal' => (float) $loanSummaries->sum('outstanding_principal'),
                'installments_collected' => (float) $installmentsPaid->sum('paid_amount'),
                'late_fees_collected' => (float) $installmentsPaid->sum('late_fee'),
                'overdue_loans' => $loanSummaries->filter(fn (array $s) => $s['overdue_count'] > 0)->count(),
            ],
            'monthly' => $this->monthly($from, $to, $paid, $payouts),
            'escrow_status' => $this->countBy(
                Transaction::query()->whereNotNull('escrow_status')->whereBetween('paid_at', [$from, $to])->get(['escrow_status']),
                fn (Transaction $t) => $t->escrow_status->value,
                ['escrow_hold', 'serah_terima', 'payout_release', 'selesai', 'dispute', 'refunded'],
            ),
            'payment_schemes' => collect(['down_payment', 'full', 'credit'])->map(fn (string $scheme) => [
                'key' => $scheme,
                'count' => $paid->filter(fn (Transaction $t) => $t->payment_scheme?->value === $scheme)->count(),
                'gmv' => (float) $paid->filter(fn (Transaction $t) => $t->payment_scheme?->value === $scheme)->sum(fn (Transaction $t) => (float) $t->vehicle_price),
            ])->values(),
            'top_brands' => $paid->groupBy(fn (Transaction $t) => $t->vehicle?->brand ?? 'Lainnya')
                ->map(fn (Collection $group, string $brand) => ['brand' => $brand, 'count' => $group->count(), 'gmv' => (float) $group->sum(fn (Transaction $t) => (float) $t->vehicle_price)])
                ->sortByDesc('gmv')->take(8)->values(),
            'verification' => [
                'seller_kyc' => $this->countBy(SellerProfile::query()->get(['status']), fn ($p) => $p->status->value, array_map(fn ($c) => $c->value, SellerProfileStatus::cases())),
                'buyer_kyc' => $this->countBy(BuyerProfile::query()->get(['status']), fn ($p) => $p->status->value, array_map(fn ($c) => $c->value, BuyerProfileStatus::cases())),
                'vehicles' => $this->countBy(Vehicle::query()->get(['status']), fn ($v) => $v->status->value, array_map(fn ($c) => $c->value, VehicleStatus::cases())),
            ],
            'tables' => [
                'sales' => $paid->map(fn (Transaction $t) => [
                    'id' => $t->id,
                    'invoice_number' => $t->invoice_number,
                    'paid_at' => $t->paid_at?->toIso8601String(),
                    'vehicle' => $t->vehicle ? "{$t->vehicle->brand} {$t->vehicle->model} {$t->vehicle->year}" : null,
                    'buyer' => $t->buyer?->name,
                    'seller' => $t->seller?->name,
                    'scheme' => $t->payment_scheme?->value,
                    'vehicle_price' => (float) $t->vehicle_price,
                    'amount' => (float) $t->amount,
                    'promo_code' => $t->promo_code,
                    'escrow_status' => $t->escrow_status?->value,
                    'payout_status' => $t->payout_status?->value,
                ])->values(),
                'payouts' => $payouts->map(fn (TransactionPayout $p) => [
                    'id' => $p->id,
                    'paid_at' => $p->paid_at?->toIso8601String(),
                    'transaction_id' => $p->transaction_id,
                    'vehicle' => $p->transaction?->vehicle ? "{$p->transaction->vehicle->brand} {$p->transaction->vehicle->model}" : null,
                    'seller' => $p->transaction?->seller?->name,
                    'method' => $p->method?->value,
                    'commission_amount' => (float) $p->commission_amount,
                    'payout_amount' => (float) $p->payout_amount,
                    'bonus_amount' => (float) $p->bonus_amount,
                    'reference' => $p->reference,
                ])->values(),
                'bonuses' => $bonuses->map(fn (SellerBonus $b) => [
                    'id' => $b->id,
                    'created_at' => $b->created_at?->toIso8601String(),
                    'seller' => $b->seller?->name,
                    'type' => $b->type,
                    'basis' => $b->basis,
                    'amount' => (float) $b->amount,
                    'transaction_id' => $b->transaction_id,
                ])->values(),
                'promos' => $redemptions->map(fn (PromoRedemption $r) => [
                    'id' => $r->id,
                    'created_at' => $r->created_at?->toIso8601String(),
                    'promo' => $r->promo?->name,
                    'code' => $r->promo?->code,
                    'benefit' => $r->promo?->benefit,
                    'buyer' => $r->user?->name,
                    'transaction_id' => $r->transaction_id,
                    'benefit_amount' => (float) $r->benefit_amount,
                ])->values(),
                'loans' => $loans->map(fn (VehicleLoan $loan) => [
                    'id' => $loan->id,
                    'contract_number' => $loan->contract_number,
                    'buyer' => $loan->transaction?->buyer?->name,
                    'vehicle' => $loan->transaction?->vehicle ? "{$loan->transaction->vehicle->brand} {$loan->transaction->vehicle->model}" : null,
                    'status' => $loan->status->value,
                    'principal' => (float) $loan->principal,
                    'tenor_months' => $loan->tenor_months,
                    'paid_count' => $loanSummaries[$loan->id]['paid_count'],
                    'outstanding_principal' => $loanSummaries[$loan->id]['outstanding_principal'],
                    'overdue_count' => $loanSummaries[$loan->id]['overdue_count'],
                    'late_fees_due' => $loanSummaries[$loan->id]['late_fees_due'],
                ])->values(),
            ],
        ]);
    }

    /**
     * @param  Collection<int, Transaction>  $paid
     * @param  Collection<int, TransactionPayout>  $payouts
     * @return array<int, array{month: string, transactions: int, gmv: float, commission: float}>
     */
    private function monthly(Carbon $from, Carbon $to, Collection $paid, Collection $payouts): array
    {
        $months = [];
        for ($cursor = $from->copy()->startOfMonth(); $cursor->lte($to); $cursor->addMonthNoOverflow()) {
            $months[$cursor->format('Y-m')] = ['month' => $cursor->format('Y-m'), 'transactions' => 0, 'gmv' => 0.0, 'commission' => 0.0];
        }

        foreach ($paid as $transaction) {
            $key = $transaction->paid_at?->format('Y-m');
            if (isset($months[$key])) {
                $months[$key]['transactions']++;
                $months[$key]['gmv'] += (float) $transaction->vehicle_price;
            }
        }

        foreach ($payouts as $payout) {
            $key = $payout->paid_at?->format('Y-m');
            if (isset($months[$key])) {
                $months[$key]['commission'] += (float) $payout->commission_amount;
            }
        }

        return array_values($months);
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @param  array<int, string>  $keys
     * @return array<int, array{key: string, count: int}>
     */
    private function countBy(Collection $rows, callable $keyOf, array $keys): array
    {
        $counts = $rows->countBy($keyOf);

        return array_map(fn (string $key) => ['key' => $key, 'count' => (int) ($counts[$key] ?? 0)], $keys);
    }
}
