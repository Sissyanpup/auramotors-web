<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleDocumentController extends Controller
{
    public function show(Request $request, Vehicle $vehicle, VehicleDocument $document): StreamedResponse
    {
        abort_unless($document->vehicle_id === $vehicle->id, 404);

        return Storage::disk('local')->response($document->path);
    }
}
