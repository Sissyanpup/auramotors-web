<?php

namespace App\Http\Resources;

use App\Models\TransactionDocument;
use App\Support\SignedDocumentUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TransactionDocument */
class TransactionDocumentResource extends JsonResource
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
            'folder' => $this->type->folder(),
            'download_url' => SignedDocumentUrl::for('transaction-documents.show', [$this->transaction_id, $this->id]),
            'generated_at' => $this->generated_at,
        ];
    }
}
