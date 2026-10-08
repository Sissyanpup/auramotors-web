<?php

namespace App\Models;

use App\Enums\VehicleDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'path'])]
class VehicleDocument extends Model
{
    protected function casts(): array
    {
        return [
            'type' => VehicleDocumentType::class,
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
