<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BuyerProfileStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BuyerKycReviewRequest;
use App\Http\Resources\BuyerProfileResource;
use App\Models\BuyerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuyerKycController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BuyerProfile::class);

        $status = $request->query('status', BuyerProfileStatus::Pending->value);

        // Eager-load user penuh (bukan partial select) supaya UserResource yang membaca
        // `bio`/`avatar_path`/`ktp_number` tidak crash karena preventAccessingMissingAttributes
        // aktif — pola bug yang sama pernah kena di SellerKycController (lihat retro Sprint 3).
        $profiles = BuyerProfile::query()
            ->with('user')
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20);

        return BuyerProfileResource::collection($profiles);
    }

    public function review(BuyerKycReviewRequest $request, BuyerProfile $buyerProfile): BuyerProfileResource
    {
        $buyerProfile->update([
            'status' => $request->validated('status'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $request->validated('status') === BuyerProfileStatus::Rejected->value
                ? $request->validated('rejection_reason')
                : null,
        ]);

        return new BuyerProfileResource($buyerProfile->fresh('user'));
    }
}
