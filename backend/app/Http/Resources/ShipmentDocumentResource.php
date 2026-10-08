<?php

namespace App\Http\Resources;

use App\Models\ShipmentDocument;
use App\Support\SignedDocumentUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShipmentDocument */
class ShipmentDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->type->label(),
            'category' => $this->type->category(),
            'download_url' => SignedDocumentUrl::for('shipments.documents.show', [$this->shipment_id, $this->id]),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
            'created_at' => $this->created_at,
        ];
    }
}
