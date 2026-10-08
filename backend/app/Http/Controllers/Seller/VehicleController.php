<?php

namespace App\Http\Controllers\Seller;

use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\VehicleStoreRequest;
use App\Http\Requests\Seller\VehicleUpdateRequest;
use App\Http\Resources\VehicleDetailResource;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Support\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VehicleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $vehicles = $request->user()->vehicles()
            ->with('coverPhoto')
            ->latest()
            ->paginate(20);

        return VehicleResource::collection($vehicles);
    }

    public function show(Request $request, Vehicle $vehicle): VehicleDetailResource
    {
        $this->authorize('view', $vehicle);

        return new VehicleDetailResource($vehicle->load('photos', 'documents', 'reviewer', 'insurancePolicies', 'vinChecks'));
    }

    public function store(VehicleStoreRequest $request): VehicleDetailResource
    {
        $vehicle = $request->user()->vehicles()->create([
            ...$request->safe()->only(['brand', 'model', 'year', 'vin', 'price', 'mileage', 'location', 'description', 'specs', 'payment_options', 'insurance_options']),
            'status' => VehicleStatus::Draft,
        ]);

        foreach ($request->file('photos', []) as $index => $photo) {
            $vehicle->photos()->create([
                'path' => $this->storeOptimizedPhoto($photo, $vehicle),
                'sort_order' => $index,
            ]);
        }

        $this->storeDocuments($request, $vehicle);

        return new VehicleDetailResource($vehicle->load('photos', 'documents', 'insurancePolicies', 'vinChecks'));
    }

    public function update(VehicleUpdateRequest $request, Vehicle $vehicle): VehicleDetailResource
    {
        $vehicle->update($request->safe()->only(['brand', 'model', 'year', 'vin', 'price', 'mileage', 'location', 'description', 'specs', 'payment_options', 'insurance_options']));

        if ($request->hasFile('photos')) {
            $vehicle->photos->each(fn ($photo) => Storage::disk('public')->delete($photo->path));
            $vehicle->photos()->delete();

            foreach ($request->file('photos') as $index => $photo) {
                $vehicle->photos()->create([
                    'path' => $this->storeOptimizedPhoto($photo, $vehicle),
                    'sort_order' => $index,
                ]);
            }
        }

        $this->storeDocuments($request, $vehicle);

        return new VehicleDetailResource($vehicle->load('photos', 'documents', 'insurancePolicies', 'vinChecks'));
    }

    public function destroy(Vehicle $vehicle): Response
    {
        $this->authorize('delete', $vehicle);

        $vehicle->delete();

        return response()->noContent();
    }

    public function submitForReview(Vehicle $vehicle): VehicleDetailResource
    {
        $this->authorize('submitForReview', $vehicle);

        abort_if($vehicle->documents()->count() < 2, 422, 'STNK dan BPKB wajib diunggah sebelum listing diajukan untuk review.');
        abort_if($vehicle->photos()->count() < 1, 422, 'Minimal 1 foto kendaraan diperlukan.');

        $vehicle->update([
            'status' => VehicleStatus::PendingReview,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);

        return new VehicleDetailResource($vehicle->load('photos', 'documents', 'insurancePolicies', 'vinChecks'));
    }

    private function storeOptimizedPhoto(UploadedFile $photo, Vehicle $vehicle): string
    {
        $path = $photo->store('vehicles/'.$vehicle->id, 'public');

        ImageOptimizer::compress(Storage::disk('public')->path($path));

        return $path;
    }

    private function storeDocuments(Request $request, Vehicle $vehicle): void
    {
        foreach (['stnk', 'bpkb'] as $type) {
            if (! $request->hasFile($type)) {
                continue;
            }

            $existing = $vehicle->documents()->where('type', $type)->first();
            if ($existing) {
                Storage::disk('local')->delete($existing->path);
                $existing->delete();
            }

            $vehicle->documents()->create([
                'type' => $type,
                'path' => $request->file($type)->store('vehicles/'.$vehicle->id.'/documents', 'local'),
            ]);
        }
    }
}
