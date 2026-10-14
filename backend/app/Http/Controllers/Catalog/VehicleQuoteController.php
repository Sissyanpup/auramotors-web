<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\PriceQuoteRequest;
use App\Models\Vehicle;
use App\Support\PriceBreakdownCalculator;
use Illuminate\Http\JsonResponse;

/**
 * Simulasi harga publik untuk halaman checkout: memakai kalkulator yang sama
 * dengan yang di-snapshot saat checkout, jadi angka pratinjau = angka tagihan.
 */
class VehicleQuoteController extends Controller
{
    public function __invoke(PriceQuoteRequest $request, Vehicle $vehicle, PriceBreakdownCalculator $pricing): JsonResponse
    {
        abort_unless($vehicle->status === VehicleStatus::Approved, 404);

        $insuranceType = $request->insuranceType();

        return response()->json([
            'data' => [
                'breakdown' => $pricing->forVehicle(
                    $vehicle,
                    $request->scheme(),
                    (float) $request->validated('dp_percent'),
                    $insuranceType,
                    $request->insuranceAddons(),
                ),
                'available_addons' => $pricing->availableAddons($insuranceType, (float) $vehicle->price),
            ],
        ]);
    }
}
