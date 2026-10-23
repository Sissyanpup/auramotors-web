<?php

namespace App\Models;

use Database\Factories\SellerBonusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['seller_id', 'transaction_id', 'transaction_payout_id', 'type', 'tier_threshold', 'amount', 'basis', 'status', 'paid_at'])]
class SellerBonus extends Model
{
    /** @use HasFactory<SellerBonusFactory> */
    use HasFactory;

    public const TYPE_SALE = 'sale';

    public const TYPE_TIER = 'tier';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tier_threshold' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(TransactionPayout::class, 'transaction_payout_id');
    }
}
