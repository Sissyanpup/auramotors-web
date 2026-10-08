<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListingReviewRequest;
use App\Http\Resources\VehicleDetailResource;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VehicleReviewController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Vehicle::class);

        $status = $request->query('status', VehicleStatus::PendingReview->value);

        $vehicles = Vehicle::query()
            ->with(['coverPhoto', 'seller:id,name,email'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20);

        return VehicleResource::collection($vehicles);
    }

    public function show(Vehicle $vehicle): VehicleDetailResource
    {
        $this->authorize('view', $vehicle);

        return new VehicleDetailResource($vehicle->load(['photos', 'documents', 'seller']));
    }

    public function review(ListingReviewRequest $request, Vehicle $vehicle): VehicleDetailResource
    {
        $vehicle->update([
            'status' => $request->validated('status'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $request->validated('status') === VehicleStatus::Rejected->value
                ? $request->validated('rejection_reason')
                : null,
        ]);

        return new VehicleDetailResource($vehicle->fresh(['photos', 'documents', 'seller']));
    }
}
