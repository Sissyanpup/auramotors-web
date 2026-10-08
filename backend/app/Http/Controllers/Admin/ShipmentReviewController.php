<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Models\Transaction;
use App\Shipping\ShipmentStateMachine;

/**
 * Admin meng-approve transisi state shipment. Endpoint terpisah per transisi
 * supaya audit jelas: bukan generic "update status" tapi aksi eksplisit dengan
 * guard-nya masing-masing di `ShipmentStateMachine`.
 */
class ShipmentReviewController extends Controller
{
    public function markLogisticsPrep(Transaction $transaction, ShipmentStateMachine $machine): ShipmentResource
    {
        $this->authorize('manage', Transaction::class);
        $shipment = $this->requireShipment($transaction);
        $machine->moveToLogisticsPrep($shipment);

        return new ShipmentResource($shipment->fresh('documents.uploader'));
    }

    public function markInTransit(Transaction $transaction, ShipmentStateMachine $machine): ShipmentResource
    {
        $this->authorize('manage', Transaction::class);
        $shipment = $this->requireShipment($transaction);
        $machine->moveToInTransit($shipment);

        return new ShipmentResource($shipment->fresh('documents.uploader'));
    }

    public function markCustomsClearance(Transaction $transaction, ShipmentStateMachine $machine): ShipmentResource
    {
        $this->authorize('manage', Transaction::class);
        $shipment = $this->requireShipment($transaction);
        $machine->moveToCustomsClearance($shipment);

        return new ShipmentResource($shipment->fresh('documents.uploader'));
    }

    public function markDelivered(Transaction $transaction, ShipmentStateMachine $machine): ShipmentResource
    {
        $this->authorize('manage', Transaction::class);
        $shipment = $this->requireShipment($transaction);
        $machine->markDelivered($shipment);

        return new ShipmentResource($shipment->fresh('documents.uploader'));
    }

    private function requireShipment(Transaction $transaction): Shipment
    {
        abort_if($transaction->shipment === null, 404, 'Shipment belum diinisiasi.');

        return $transaction->shipment;
    }
}
