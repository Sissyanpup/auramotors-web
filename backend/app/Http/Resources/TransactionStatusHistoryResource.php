<?php

namespace App\Http\Resources;

use App\Models\TransactionStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TransactionStatusHistory */
class TransactionStatusHistoryResource extends JsonResource
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
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            // Not gated by whenLoaded(): Eloquent doesn't mark a nullable
            // BelongsTo as "loaded" when the foreign key is null (system
            // actions), so whenLoaded() would silently drop this key.
            'actor' => $this->actor?->name ?? 'Sistem',
            'note' => $this->note,
            'created_at' => $this->created_at,
        ];
    }
}
