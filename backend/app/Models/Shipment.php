<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'transaction_id',
    'origin_country', 'destination_country', 'shipping_mode', 'carrier_name',
    'tracking_number', 'estimated_arrival', 'actual_arrival',
    'cargo_insurer_name', 'cargo_policy_number', 'cargo_coverage_amount', 'cargo_certificate_path',
    'status',
])]
class Shipment extends Model
{
    protected function casts(): array
    {
        return [
            'shipping_mode' => ShippingMode::class,
            'status' => ShipmentStatus::class,
            'cargo_coverage_amount' => 'decimal:2',
            'estimated_arrival' => 'date',
            'actual_arrival' => 'date',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ShipmentDocument::class)->latest();
    }

    /**
     * Cargo insurance dianggap "tersedia" kalau semua 4 field polis terisi
     * (nomor, insurer, coverage, sertifikat).
     */
    public function hasCargoInsurance(): bool
    {
        return filled($this->cargo_insurer_name)
            && filled($this->cargo_policy_number)
            && $this->cargo_coverage_amount !== null
            && filled($this->cargo_certificate_path);
    }
}
