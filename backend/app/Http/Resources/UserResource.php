<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
            'role' => $this->role,
            'ktp_number' => $this->ktp_number,
            'ktp_name' => $this->ktp_name,
            'has_completed_ktp' => $this->hasCompletedKtp(),
            'seller_profile_status' => $this->whenLoaded('sellerProfile', fn () => $this->sellerProfile?->status),
        ];
    }
}
