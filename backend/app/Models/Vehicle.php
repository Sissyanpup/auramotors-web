<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use App\Support\CatalogCache;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['brand', 'model', 'year', 'vin', 'price', 'mileage', 'location', 'description', 'specs', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'payment_options', 'insurance_options'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, SoftDeletes;

    /** Preset default DP jika seller tidak mengoverride. */
    public const DEFAULT_PAYMENT_OPTIONS = [
        ['label' => 'DP 5%', 'percent' => 5],
        ['label' => 'DP 10%', 'percent' => 10],
        ['label' => 'DP 15%', 'percent' => 15],
        ['label' => 'DP 20%', 'percent' => 20],
        ['label' => 'Bayar Penuh', 'percent' => 100],
    ];

    /** Preset default asuransi jika seller tidak mengoverride. Premi = persentase dari harga kendaraan. */
    public const DEFAULT_INSURANCE_OPTIONS = [
        ['type' => 'none', 'label' => 'Tanpa Asuransi', 'premium_percent' => 0],
        ['type' => 'tlo', 'label' => 'TLO (Total Loss Only)', 'premium_percent' => 0.35],
        ['type' => 'all_risk', 'label' => 'All Risk', 'premium_percent' => 2.5],
    ];

    protected $attributes = [
        'description' => null,
        'specs' => null,
        'reviewed_by' => null,
        'reviewed_at' => null,
        'rejection_reason' => null,
        'payment_options' => null,
        'insurance_options' => null,
    ];

    /**
     * Bump the catalog cache version on any write so cached public catalog
     * responses (see App\Support\CatalogCache) never serve stale data after
     * a status change (approve/reject/sold/etc).
     */
    protected static function booted(): void
    {
        static::saved(fn () => CatalogCache::bump());
        static::deleted(fn () => CatalogCache::bump());
    }

    protected function casts(): array
    {
        return [
            'specs' => 'array',
            'payment_options' => 'array',
            'insurance_options' => 'array',
            'status' => VehicleStatus::class,
            'reviewed_at' => 'datetime',
            'price' => 'decimal:2',
        ];
    }

    /**
     * Preset DP efektif — seller override kalau ada, default kalau tidak.
     *
     * @return array<int, array{label:string, percent:float|int}>
     */
    public function effectivePaymentOptions(): array
    {
        return $this->payment_options ?: self::DEFAULT_PAYMENT_OPTIONS;
    }

    /**
     * Preset asuransi efektif — seller override kalau ada, default kalau tidak.
     * Premi dihitung dari persentase harga kendaraan (kecuali seller isi premium eksplisit).
     *
     * @return array<int, array{type:string, label:string, premium:float}>
     */
    public function effectiveInsuranceOptions(): array
    {
        $options = $this->insurance_options ?: self::DEFAULT_INSURANCE_OPTIONS;
        $price = (float) $this->price;

        return array_map(function (array $opt) use ($price): array {
            $premium = isset($opt['premium'])
                ? (float) $opt['premium']
                : $price * ((float) ($opt['premium_percent'] ?? 0)) / 100;

            return [
                'type' => (string) $opt['type'],
                'label' => (string) $opt['label'],
                'premium' => round($premium, 2),
            ];
        }, $options);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(VehiclePhoto::class)->orderBy('sort_order');
    }

    /**
     * Single cheap relation for list views that only need a thumbnail,
     * instead of eager-loading every photo just to show the first one.
     */
    public function coverPhoto(): HasOne
    {
        return $this->hasOne(VehiclePhoto::class)->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function insurancePolicies(): HasMany
    {
        return $this->hasMany(VehicleInsurancePolicy::class)->latest('valid_until');
    }

    public function vinChecks(): HasMany
    {
        return $this->hasMany(VehicleVinCheck::class)->latest('checked_at');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', VehicleStatus::Approved);
    }
}
