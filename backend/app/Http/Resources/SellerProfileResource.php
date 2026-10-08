<?php

namespace App\Http\Resources;

use App\Models\SellerProfile;
use App\Support\SignedDocumentUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SellerProfile */
class SellerProfileResource extends JsonResource
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
            'user' => new UserResource($this->whenLoaded('user')),
            'entity_type' => $this->entity_type,
            'status' => $this->status,
            'ktp_url' => SignedDocumentUrl::for('seller-kyc.documents.show', [$this->id, 'ktp']),
            'npwp_url' => $this->npwp_path ? SignedDocumentUrl::for('seller-kyc.documents.show', [$this->id, 'npwp']) : null,
            'company_registration_url' => $this->company_registration_path ? SignedDocumentUrl::for('seller-kyc.documents.show', [$this->id, 'company_registration']) : null,
            'articles_of_association_url' => $this->articles_of_association_path ? SignedDocumentUrl::for('seller-kyc.documents.show', [$this->id, 'articles_of_association']) : null,
            'ubo_declaration_url' => $this->ubo_declaration_path ? SignedDocumentUrl::for('seller-kyc.documents.show', [$this->id, 'ubo_declaration']) : null,
            'reviewed_by' => $this->reviewer?->name,
            'reviewed_at' => $this->reviewed_at,
            'rejection_reason' => $this->rejection_reason,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'bank_account_holder_name' => $this->bank_account_holder_name,
            'created_at' => $this->created_at,
        ];
    }
}
