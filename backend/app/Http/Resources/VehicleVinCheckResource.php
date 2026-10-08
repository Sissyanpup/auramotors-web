<?php

namespace App\Http\Resources;

use App\Models\VehicleVinCheck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VehicleVinCheck */
class VehicleVinCheckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vin' => $this->vin,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'report' => $this->report,
            'checked_at' => $this->checked_at,
        ];
    }
}
