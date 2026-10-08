<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\VinCheckRequest;
use App\Http\Resources\VehicleVinCheckResource;
use App\Models\Vehicle;
use App\VinCheck\VinCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VehicleVinCheckController extends Controller
{
    public function index(Vehicle $vehicle): AnonymousResourceCollection
    {
        $this->authorize('update', $vehicle);

        return VehicleVinCheckResource::collection($vehicle->vinChecks);
    }

    public function store(VinCheckRequest $request, Vehicle $vehicle, VinCheckService $service): JsonResponse
    {
        $this->authorize('update', $vehicle);

        // Sinkronkan VIN kendaraan dengan yang di-cek (kalau belum diisi atau berubah).
        if ($vehicle->vin !== strtoupper($request->validated('vin'))) {
            $vehicle->update(['vin' => strtoupper($request->validated('vin'))]);
        }

        $check = $service->check($vehicle, $request->validated('vin'));

        return (new VehicleVinCheckResource($check))->response()->setStatusCode(201);
    }
}
