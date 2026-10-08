<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use App\Enums\TransactionPayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transaction_id', 'method', 'status', 'commission_rate', 'commission_amount',
    'payout_amount', 'reference', 'failure_reason', 'initiated_by', 'paid_at',
])]
class TransactionPayout extends Model
{
    protected $attributes = [
        'reference' => null,
        'failure_reason' => null,
        'paid_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'method' => PayoutMethod::class,
            'status' => TransactionPayoutStatus::class,
            'commission_rate' => 'decimal:4',
            'commission_amount' => 'decimal:2',
            'payout_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
