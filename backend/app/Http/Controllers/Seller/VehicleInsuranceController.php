<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\VehicleInsurancePolicyRequest;
use App\Http\Resources\VehicleInsurancePolicyResource;
use App\Models\Vehicle;
use App\Models\VehicleInsurancePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class VehicleInsuranceController extends Controller
{
    public function index(Vehicle $vehicle): AnonymousResourceCollection
    {
        $this->authorize('update', $vehicle);

        return VehicleInsurancePolicyResource::collection($vehicle->insurancePolicies);
    }

    public function store(VehicleInsurancePolicyRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $path = $request->file('certificate')->store('vehicle-insurance/'.$vehicle->id, 'local');

        $policy = $vehicle->insurancePolicies()->create([
            'policy_type' => $request->validated('policy_type'),
            'insurer_name' => $request->validated('insurer_name'),
            'policy_number' => $request->validated('policy_number'),
            'coverage_amount' => $request->validated('coverage_amount'),
            'agreed_value_amount' => $request->validated('agreed_value_amount'),
            'valid_from' => $request->validated('valid_from'),
            'valid_until' => $request->validated('valid_until'),
            'certificate_path' => $path,
        ]);

        return (new VehicleInsurancePolicyResource($policy))->response()->setStatusCode(201);
    }

    public function destroy(Vehicle $vehicle, VehicleInsurancePolicy $policy): JsonResponse
    {
        $this->authorize('update', $vehicle);
        abort_unless($policy->vehicle_id === $vehicle->id, 404);

        Storage::disk('local')->delete($policy->certificate_path);
        $policy->delete();

        return response()->json(['deleted' => true]);
    }
}
