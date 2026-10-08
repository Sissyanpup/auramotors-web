<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use App\Support\SignedDocumentUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Vehicle */
class VehicleDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'vin' => $this->vin,
            'price' => $this->price,
            'mileage' => $this->mileage,
            'location' => $this->location,
            'description' => $this->description,
            'specs' => $this->specs,
            'payment_options' => $this->effectivePaymentOptions(),
            'insurance_options' => $this->effectiveInsuranceOptions(),
            'status' => $this->status,
            'reviewed_by' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
            'reviewed_at' => $this->reviewed_at,
            'rejection_reason' => $this->rejection_reason,
            'seller' => $this->whenLoaded('seller', fn () => [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
            ]),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => Storage::disk('public')->url($photo->path),
            ])),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($document) => [
                'id' => $document->id,
                'type' => $document->type,
                'download_url' => SignedDocumentUrl::for('vehicles.documents.show', [$this->id, $document->id]),
            ])),
            'insurance_policies' => $this->whenLoaded(
                'insurancePolicies',
                fn () => VehicleInsurancePolicyResource::collection($this->insurancePolicies)
            ),
            'latest_vin_check' => $this->whenLoaded(
                'vinChecks',
                fn () => $this->vinChecks->first()
                    ? new VehicleVinCheckResource($this->vinChecks->first())
                    : null
            ),
            'created_at' => $this->created_at,
        ];
    }
}
