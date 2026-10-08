<?php

namespace App\Http\Resources;

use App\Models\Shipment;
use App\Support\SignedDocumentUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Shipment */
class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'origin_country' => $this->origin_country,
            'destination_country' => $this->destination_country,
            'shipping_mode' => $this->shipping_mode,
            'shipping_mode_label' => $this->shipping_mode->label(),
            'carrier_name' => $this->carrier_name,
            'tracking_number' => $this->tracking_number,
            'estimated_arrival' => $this->estimated_arrival?->toDateString(),
            'actual_arrival' => $this->actual_arrival?->toDateString(),
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'cargo_insurance' => [
                'insurer_name' => $this->cargo_insurer_name,
                'policy_number' => $this->cargo_policy_number,
                'coverage_amount' => $this->cargo_coverage_amount,
                'certificate_url' => $this->cargo_certificate_path
                    ? SignedDocumentUrl::for('shipments.cargo-certificate.show', [$this->id])
                    : null,
                'is_complete' => $this->hasCargoInsurance(),
            ],
            'documents' => ShipmentDocumentResource::collection($this->whenLoaded('documents')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
