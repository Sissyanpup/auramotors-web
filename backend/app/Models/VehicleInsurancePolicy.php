<?php

namespace App\Models;

use App\Enums\VehiclePolicyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'vehicle_id', 'policy_type', 'insurer_name', 'policy_number',
    'coverage_amount', 'agreed_value_amount',
    'valid_from', 'valid_until', 'certificate_path',
])]
class VehicleInsurancePolicy extends Model
{
    protected function casts(): array
    {
        return [
            'policy_type' => VehiclePolicyType::class,
            'coverage_amount' => 'decimal:2',
            'agreed_value_amount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function isActive(): bool
    {
        $today = now()->startOfDay();

        return $this->valid_from->lessThanOrEqualTo($today)
            && $this->valid_until->greaterThanOrEqualTo($today);
    }
}
