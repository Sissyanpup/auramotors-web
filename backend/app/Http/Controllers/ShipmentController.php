<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShipmentUpsertRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Init / update data shipment untuk transaksi. Buyer atau seller boleh
 * memulai shipment (siapa yang koordinasi logistik bisa keduanya). Admin
 * memantau & meng-approve transisi state (di controller terpisah).
 */
class ShipmentController extends Controller
{
    public function show(Request $request, Transaction $transaction): ShipmentResource
    {
        $this->authorize('view', $transaction);
        abort_if($transaction->shipment === null, 404, 'Shipment belum diinisiasi.');

        return new ShipmentResource($transaction->shipment->load('documents.uploader'));
    }

    public function store(ShipmentUpsertRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $transaction);
        abort_if($transaction->shipment !== null, 409, 'Shipment sudah diinisiasi untuk transaksi ini.');

        $data = $request->safe()->except('cargo_certificate');
        $data['transaction_id'] = $transaction->id;
        if ($request->hasFile('cargo_certificate')) {
            $data['cargo_certificate_path'] = $request->file('cargo_certificate')
                ->store('shipments/'.$transaction->id.'/cargo', 'local');
        }

        $shipment = Shipment::create($data);

        return (new ShipmentResource($shipment->fresh()))->response()->setStatusCode(201);
    }

    public function update(ShipmentUpsertRequest $request, Transaction $transaction): ShipmentResource
    {
        $this->authorize('view', $transaction);
        $shipment = $transaction->shipment;
        abort_if($shipment === null, 404, 'Shipment belum diinisiasi.');

        $data = $request->safe()->except('cargo_certificate');
        if ($request->hasFile('cargo_certificate')) {
            if ($shipment->cargo_certificate_path) {
                Storage::disk('local')->delete($shipment->cargo_certificate_path);
            }
            $data['cargo_certificate_path'] = $request->file('cargo_certificate')
                ->store('shipments/'.$transaction->id.'/cargo', 'local');
        }

        $shipment->update($data);

        return new ShipmentResource($shipment->fresh('documents.uploader'));
    }
}
