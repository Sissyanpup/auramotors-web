<?php

namespace App\Models;

use Database\Factories\PromoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'code', 'kind', 'benefit', 'discount_type', 'discount_value', 'max_discount',
    'addon_code', 'rate_cut', 'min_vehicle_price', 'brand', 'badge', 'description',
    'starts_at', 'ends_at', 'quota', 'used_count', 'is_active',
])]
class Promo extends Model
{
    /** @use HasFactory<PromoFactory> */
    use HasFactory;

    public const KIND_AUTO = 'auto';

    public const KIND_VOUCHER = 'voucher';

    public const BENEFIT_DISCOUNT = 'discount';

    public const BENEFIT_FREE_ADDON = 'free_addon';

    public const BENEFIT_CREDIT_RATE = 'credit_rate';

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'rate_cut' => 'decimal:4',
            'min_vehicle_price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'quota' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Promo yang sedang berjalan: aktif, dalam periode, dan kuota masih ada.
     *
     * @param  Builder<Promo>  $query
     */
    public function scopeRunning(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(fn (Builder $q) => $q->whereNull('quota')->orWhereColumn('used_count', '<', 'quota'));
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoRedemption::class);
    }
}
