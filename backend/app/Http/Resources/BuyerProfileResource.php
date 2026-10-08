<?php

namespace App\Http\Resources;

use App\Models\BuyerProfile;
use App\Support\SignedDocumentUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BuyerProfile */
class BuyerProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'id_type' => $this->id_type,
            'id_number' => $this->id_number,
            'status' => $this->status,
            'id_document_url' => SignedDocumentUrl::for('buyer-kyc.documents.show', [$this->id, 'id_document']),
            'address_proof_url' => SignedDocumentUrl::for('buyer-kyc.documents.show', [$this->id, 'address_proof']),
            'proof_of_funds_url' => SignedDocumentUrl::for('buyer-kyc.documents.show', [$this->id, 'proof_of_funds']),
            'reviewed_by' => $this->reviewer?->name,
            'reviewed_at' => $this->reviewed_at,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
        ];
    }
}
