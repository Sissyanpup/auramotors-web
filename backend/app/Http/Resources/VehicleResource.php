<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use App\Promotions\PromoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Vehicle */
class VehicleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cover = $this->relationLoaded('coverPhoto')
            ? $this->coverPhoto
            : $this->whenLoaded('photos', fn () => $this->photos->first());

        return [
            'id' => $this->id,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'price' => $this->price,
            // Promo potongan harga terbaik yang sedang berjalan — ditampilkan sebagai harga coret.
            'promo' => app(PromoService::class)->catalogPromo($this->resource),
            'mileage' => $this->mileage,
            'location' => $this->location,
            'status' => $this->status,
            'cover_photo_url' => $cover ? Storage::disk('public')->url($cover->path) : null,
            'seller_name' => $this->whenLoaded('seller', fn () => $this->seller->name),
            // Jejak verifikasi listing — hanya untuk tabel review admin.
            $this->mergeWhen($request->user()?->isAdmin() ?? false, fn () => [
                'reviewed_by' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
                'reviewed_at' => $this->reviewed_at,
                'rejection_reason' => $this->rejection_reason,
                'created_at' => $this->created_at,
            ]),
        ];
    }
}
