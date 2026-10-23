<?php

namespace App\Models;

use App\Enums\LoanStatus;
use Database\Factories\VehicleLoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'transaction_id', 'lender_name', 'contract_number', 'status',
    'vehicle_financed', 'insurance_financed', 'principal', 'flat_rate', 'tenor_months',
    'interest_total', 'monthly_installment', 'total_payable', 'effective_rate',
    'submitted_at', 'auto_approve_at', 'approved_at', 'approved_by',
    'lender_disbursed_at', 'paid_off_at', 'clock_offset_months',
])]
class VehicleLoan extends Model
{
    /** @use HasFactory<VehicleLoanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'vehicle_financed' => 'decimal:2',
            'insurance_financed' => 'decimal:2',
            'principal' => 'decimal:2',
            'flat_rate' => 'decimal:4',
            'interest_total' => 'decimal:2',
            'monthly_installment' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'effective_rate' => 'decimal:4',
            'submitted_at' => 'datetime',
            'auto_approve_at' => 'datetime',
            'approved_at' => 'datetime',
            'lender_disbursed_at' => 'datetime',
            'paid_off_at' => 'datetime',
            'clock_offset_months' => 'integer',
        ];
    }

    /**
     * "Hari ini" versi jam simulasi — dipakai untuk status jatuh tempo & denda.
     */
    public function today(): Carbon
    {
        return now()->addMonthsNoOverflow($this->clock_offset_months)->startOfDay();
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('sequence');
    }
}
