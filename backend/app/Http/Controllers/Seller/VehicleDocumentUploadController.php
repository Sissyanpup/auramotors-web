<?php

namespace App\Http\Controllers\Seller;

use App\Enums\VehicleDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\VehicleDocumentUploadRequest;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Support\SignedDocumentUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class VehicleDocumentUploadController extends Controller
{
    /**
     * Upload / ganti dokumen kendaraan (STNK, BPKB, Service History, Inspection
     * Report, Certificate of Authenticity). Kalau tipe yang sama sudah ada,
     * dokumen lama dihapus supaya tidak menumpuk versi ganda.
     */
    public function store(VehicleDocumentUploadRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $type = VehicleDocumentType::from($request->validated('type'));

        // Hapus dokumen tipe yang sama supaya tidak duplikat.
        $existing = $vehicle->documents()->where('type', $type)->get();
        foreach ($existing as $doc) {
            Storage::disk('local')->delete($doc->path);
            $doc->delete();
        }

        $path = $request->file('file')->store('vehicle-documents/'.$vehicle->id, 'local');
        $document = $vehicle->documents()->create(['type' => $type, 'path' => $path]);

        return response()->json([
            'id' => $document->id,
            'type' => $document->type,
            'download_url' => SignedDocumentUrl::for('vehicles.documents.show', [$vehicle->id, $document->id]),
        ], 201);
    }

    public function destroy(Vehicle $vehicle, VehicleDocument $document): JsonResponse
    {
        $this->authorize('update', $vehicle);
        abort_unless($document->vehicle_id === $vehicle->id, 404);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return response()->json(['deleted' => true]);
    }
}
