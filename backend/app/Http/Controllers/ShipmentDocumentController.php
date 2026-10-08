<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentDocumentType;
use App\Http\Requests\ShipmentDocumentUploadRequest;
use App\Http\Resources\ShipmentDocumentResource;
use App\Models\Shipment;
use App\Models\ShipmentDocument;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShipmentDocumentController extends Controller
{
    public function store(ShipmentDocumentUploadRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $transaction);
        $shipment = $transaction->shipment;
        abort_if($shipment === null, 404, 'Shipment belum diinisiasi.');

        $type = ShipmentDocumentType::from($request->validated('type'));
        $user = $request->user();

        // Gate uploader role: dokumen impor/customs hanya boleh oleh admin,
        // proof of delivery/acceptance oleh buyer, sisanya seller.
        $expectedRole = $type->uploaderRole();
        $actualRole = match (true) {
            $user->isAdmin() => 'admin',
            $user->id === $transaction->buyer_id => 'buyer',
            $user->id === $transaction->seller_id => 'seller',
            default => 'unknown',
        };
        // Admin selalu boleh upload apa pun sebagai override.
        abort_unless(
            $expectedRole === $actualRole || $user->isAdmin(),
            403,
            'Peran Anda tidak diperkenankan mengunggah dokumen tipe ini.'
        );

        // Replace-not-append per tipe.
        foreach ($shipment->documents()->where('type', $type)->get() as $doc) {
            Storage::disk('local')->delete($doc->path);
            $doc->delete();
        }

        $path = $request->file('file')->store('shipments/'.$shipment->id.'/documents', 'local');
        $document = $shipment->documents()->create([
            'type' => $type,
            'path' => $path,
            'uploaded_by' => $user->id,
        ]);

        return (new ShipmentDocumentResource($document->load('uploader')))->response()->setStatusCode(201);
    }

    public function show(Shipment $shipment, ShipmentDocument $document): StreamedResponse
    {
        abort_unless($document->shipment_id === $shipment->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->response($document->path);
    }

    public function cargoCertificate(Shipment $shipment): StreamedResponse
    {
        abort_if($shipment->cargo_certificate_path === null, 404);
        abort_unless(Storage::disk('local')->exists($shipment->cargo_certificate_path), 404);

        return Storage::disk('local')->response($shipment->cargo_certificate_path);
    }
}
