<?php

namespace App\Credit;

use App\Activity\ActivityLogger;
use App\Enums\LoanStatus;
use App\Enums\PaymentScheme;
use App\Models\LoanInstallment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VehicleLoan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Siklus hidup kredit leasing (simulasi, proyek offline):
 *
 *   pembeli bayar DP + biaya kredit ke escrow → pengajuan terkirim (submitted)
 *   → leasing menyetujui (otomatis setelah jeda, atau manual oleh admin)
 *   → leasing melunasi sisa harga ke penjual & jadwal angsuran terbit (approved)
 *   → pembeli mencicil ke leasing lewat portal bank → lunas (paid_off).
 *
 * Status tiap angsuran (lunas/jatuh tempo/terlambat) dan dendanya dihitung
 * dari "hari ini" versi jam simulasi pinjaman, supaya admin bisa mendemokan
 * keterlambatan dengan tombol "majukan 1 bulan".
 */
class CreditService
{
    private const DUE_SOON_DAYS = 7;

    /**
     * Dipanggil setelah pembayaran DP masuk escrow. Angka pinjaman disalin dari
     * snapshot rincian harga supaya sama persis dengan yang disetujui pembeli.
     */
    public function openApplication(Transaction $transaction): ?VehicleLoan
    {
        $plan = $transaction->price_breakdown['credit'] ?? null;

        if ($transaction->payment_scheme !== PaymentScheme::Credit || $plan === null || $transaction->loan()->exists()) {
            return null;
        }

        return $transaction->loan()->create([
            'lender_name' => $plan['lender'],
            'contract_number' => sprintf('PK/AURA/%s/%06d-%s', now()->format('Y'), $transaction->id, Str::upper(Str::random(3))),
            'status' => LoanStatus::Submitted,
            'vehicle_financed' => $plan['vehicle_financed'],
            'insurance_financed' => $plan['insurance_financed'],
            'principal' => $plan['principal'],
            'flat_rate' => $plan['flat_rate'],
            'tenor_months' => $plan['tenor_months'],
            'interest_total' => $plan['interest_total'],
            'monthly_installment' => $plan['monthly_installment'],
            'total_payable' => $plan['total_payable'],
            'effective_rate' => $plan['effective_rate'],
            'submitted_at' => now(),
            'auto_approve_at' => now()->addSeconds((int) config('pricing.credit.auto_approve_seconds')),
        ]);
    }

    /**
     * Persetujuan otomatis leasing setelah jeda analisis berlalu — dicek
     * setiap kali data pinjaman dibuka (tanpa queue worker, proyek offline).
     */
    public function syncAutoApproval(?VehicleLoan $loan): void
    {
        if ($loan !== null && $loan->status === LoanStatus::Submitted && now()->greaterThanOrEqualTo($loan->auto_approve_at)) {
            ActivityLogger::asSystem(fn () => $this->approve($loan, null));
        }
    }

    /**
     * Leasing menyetujui: sisa harga dilunasi ke penjual dan jadwal angsuran
     * terbit. Angsuran pertama jatuh tempo sebulan setelah persetujuan (ADDB).
     */
    public function approve(VehicleLoan $loan, ?User $admin): void
    {
        abort_unless($loan->status === LoanStatus::Submitted, 409, 'Pengajuan kredit ini sudah diproses.');

        DB::transaction(function () use ($loan, $admin) {
            $approvedAt = now();
            $loan->update([
                'status' => LoanStatus::Approved,
                'approved_at' => $approvedAt,
                'approved_by' => $admin?->id,
                'lender_disbursed_at' => $approvedAt,
            ]);

            $tenor = (int) $loan->tenor_months;
            $principal = (float) $loan->principal;
            $monthly = (float) $loan->monthly_installment;
            $principalPerMonth = round($principal / $tenor, 0);

            for ($sequence = 1; $sequence <= $tenor; $sequence++) {
                $principalPortion = $sequence === $tenor
                    ? $principal - $principalPerMonth * ($tenor - 1)
                    : $principalPerMonth;

                $loan->installments()->create([
                    'sequence' => $sequence,
                    'due_date' => $approvedAt->copy()->startOfDay()->addMonthsNoOverflow($sequence)->toDateString(),
                    'principal_amount' => $principalPortion,
                    'interest_amount' => $monthly - $principalPortion,
                    'amount' => $monthly,
                ]);
            }
        });
    }

    /**
     * @return array{status: 'paid'|'overdue'|'due'|'upcoming', days_late: int, late_fee: float, amount_due: float}
     */
    public function installmentState(LoanInstallment $installment, VehicleLoan $loan): array
    {
        if ($installment->paid_at !== null) {
            return [
                'status' => 'paid',
                'days_late' => 0,
                'late_fee' => (float) $installment->late_fee,
                'amount_due' => 0.0,
            ];
        }

        $today = $loan->today();
        $dueDate = $installment->due_date->copy()->startOfDay();
        $daysLate = $today->greaterThan($dueDate) ? (int) $dueDate->diffInDays($today) : 0;
        $lateFee = round((float) $installment->amount * (float) config('pricing.credit.late_fee_daily_rate') * $daysLate, 0);

        $status = match (true) {
            $daysLate > 0 => 'overdue',
            $today->greaterThanOrEqualTo($dueDate->copy()->subDays(self::DUE_SOON_DAYS)) => 'due',
            default => 'upcoming',
        };

        return [
            'status' => $status,
            'days_late' => $daysLate,
            'late_fee' => $lateFee,
            'amount_due' => (float) $installment->amount + $lateFee,
        ];
    }

    public function nextPayable(VehicleLoan $loan): ?LoanInstallment
    {
        return $loan->installments->first(fn (LoanInstallment $installment) => $installment->paid_at === null);
    }

    /**
     * Bayar angsuran berikutnya — lewat portal bank (pembeli) atau dicatat
     * manual oleh admin (setoran tunai di kantor leasing). Angsuran harus
     * dibayar berurutan, denda dihitung pada saat bayar.
     *
     * @param  array{payment_method?: string|null, payment_channel?: string|null, payment_account_masked?: string|null}  $paymentDetails
     */
    public function pay(LoanInstallment $installment, array $paymentDetails, ?User $recordedBy = null, ?string $note = null): void
    {
        $loan = $installment->loan()->with('installments')->firstOrFail();

        abort_unless($loan->status === LoanStatus::Approved, 409, 'Kredit belum disetujui atau sudah lunas.');
        abort_if($installment->paid_at !== null, 409, 'Angsuran ini sudah dibayar.');
        abort_unless($this->nextPayable($loan)?->is($installment), 422, 'Angsuran harus dibayar berurutan — lunasi angsuran sebelumnya terlebih dahulu.');

        $state = $this->installmentState($installment, $loan);

        DB::transaction(function () use ($installment, $loan, $state, $paymentDetails, $recordedBy, $note) {
            $installment->update([
                'paid_at' => now(),
                'late_fee' => $state['late_fee'],
                'paid_amount' => $state['amount_due'],
                'payment_method' => $paymentDetails['payment_method'] ?? null,
                'payment_channel' => $paymentDetails['payment_channel'] ?? null,
                'payment_account_masked' => $paymentDetails['payment_account_masked'] ?? null,
                'payment_approval_code' => Str::upper(Str::random(6)),
                'recorded_by' => $recordedBy?->id,
                'note' => $note,
            ]);

            if ($loan->installments()->whereNull('paid_at')->doesntExist()) {
                $loan->update(['status' => LoanStatus::PaidOff, 'paid_off_at' => now()]);
            }
        });
    }

    /**
     * Jam simulasi: majukan "hari ini" pinjaman satu bulan untuk demo jatuh
     * tempo & denda tanpa menunggu berbulan-bulan.
     */
    public function advanceClock(VehicleLoan $loan, int $months = 1): void
    {
        abort_unless($loan->status === LoanStatus::Approved, 409, 'Jam simulasi hanya berlaku untuk kredit yang sedang berjalan.');

        $loan->update(['clock_offset_months' => $loan->clock_offset_months + $months]);
    }

    /**
     * @return array{paid_count: int, outstanding_principal: float, outstanding_amount: float, overdue_count: int, late_fees_due: float, next_due_date: string|null}
     */
    public function summary(VehicleLoan $loan): array
    {
        $loan->loadMissing('installments');
        $unpaid = $loan->installments->whereNull('paid_at');
        $states = $unpaid->map(fn (LoanInstallment $installment) => $this->installmentState($installment, $loan));

        return [
            'paid_count' => $loan->installments->count() - $unpaid->count(),
            'outstanding_principal' => (float) $unpaid->sum('principal_amount'),
            'outstanding_amount' => (float) $unpaid->sum('amount'),
            'overdue_count' => $states->where('status', 'overdue')->count(),
            'late_fees_due' => (float) $states->sum('late_fee'),
            'next_due_date' => $unpaid->first()?->due_date?->toDateString(),
        ];
    }
}
