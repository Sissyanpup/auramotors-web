<?php

namespace App\Http\Resources;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ActivityLog */
class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'actor' => $this->actor_id === null ? null : [
                'id' => $this->actor_id,
                'name' => $this->actor?->name ?? "User #{$this->actor_id}",
                'role' => $this->actor_role,
            ],
            'category' => $this->category,
            'category_label' => $this->category->label(),
            'action' => $this->action,
            'description' => $this->description,
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,
            'transaction_id' => $this->transaction_id,
            'properties' => $this->properties,
            'ip_address' => $this->ip_address,
        ];
    }
}
