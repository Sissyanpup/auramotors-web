<?php

namespace App\Models;

use App\Enums\VinCheckStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vehicle_id', 'vin', 'status', 'report', 'checked_at'])]
class VehicleVinCheck extends Model
{
    protected function casts(): array
    {
        return [
            'status' => VinCheckStatus::class,
            'report' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
