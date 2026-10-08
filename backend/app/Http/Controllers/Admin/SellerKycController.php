<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerProfileStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KycReviewRequest;
use App\Http\Resources\SellerProfileResource;
use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SellerKycController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SellerProfile::class);

        $status = $request->query('status', SellerProfileStatus::Pending->value);

        // Eager-load user penuh — partial select (`user:id,name,email,role`) memicu
        // MissingAttributeException di UserResource yang mengakses `bio`/`avatar_path`/`ktp_number`
        // karena `Model::preventAccessingMissingAttributes` aktif. Sudah dinormalkan
        // dengan BuyerKycController.
        $profiles = SellerProfile::query()
            ->with('user')
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20);

        return SellerProfileResource::collection($profiles);
    }

    public function review(KycReviewRequest $request, SellerProfile $sellerProfile): SellerProfileResource
    {
        $sellerProfile->update([
            'status' => $request->validated('status'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $request->validated('status') === SellerProfileStatus::Rejected->value
                ? $request->validated('rejection_reason')
                : null,
        ]);

        return new SellerProfileResource($sellerProfile->fresh('user'));
    }
}
