<?php

namespace App\Models;

use App\Enums\ShipmentDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shipment_id', 'type', 'path', 'uploaded_by'])]
class ShipmentDocument extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ShipmentDocumentType::class,
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
