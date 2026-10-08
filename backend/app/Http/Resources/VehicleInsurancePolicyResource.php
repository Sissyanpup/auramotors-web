<?php

namespace App\Http\Resources;

use App\Models\VehicleInsurancePolicy;
use App\Support\SignedDocumentUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VehicleInsurancePolicy */
class VehicleInsurancePolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'policy_type' => $this->policy_type,
            'policy_type_label' => $this->policy_type->label(),
            'insurer_name' => $this->insurer_name,
            'policy_number' => $this->policy_number,
            'coverage_amount' => $this->coverage_amount,
            'agreed_value_amount' => $this->agreed_value_amount,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'is_active' => $this->isActive(),
            'certificate_url' => SignedDocumentUrl::for('vehicles.insurance.certificate', [$this->vehicle_id, $this->id]),
            'created_at' => $this->created_at,
        ];
    }
}
