<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleInsurancePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleInsuranceCertificateController extends Controller
{
    public function show(Request $request, Vehicle $vehicle, VehicleInsurancePolicy $policy): StreamedResponse
    {
        abort_unless($policy->vehicle_id === $vehicle->id, 404);
        abort_unless(Storage::disk('local')->exists($policy->certificate_path), 404);

        return Storage::disk('local')->response($policy->certificate_path);
    }
}
