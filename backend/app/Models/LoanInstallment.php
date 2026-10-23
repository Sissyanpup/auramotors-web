<?php

namespace App\Models;

use Database\Factories\LoanInstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'vehicle_loan_id', 'sequence', 'due_date', 'principal_amount', 'interest_amount', 'amount',
    'paid_at', 'late_fee', 'paid_amount',
    'payment_method', 'payment_channel', 'payment_account_masked', 'payment_approval_code',
    'recorded_by', 'note',
])]
class LoanInstallment extends Model
{
    /** @use HasFactory<LoanInstallmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_date' => 'date',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'late_fee' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(VehicleLoan::class, 'vehicle_loan_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
