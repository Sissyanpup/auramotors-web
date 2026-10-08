<?php

namespace App\Shipping;

use App\Enums\ShipmentDocumentType;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;

/**
 * Aturan transisi state pengiriman cross-border (Sprint 9 / Epic 9). Terpisah
 * dari `EscrowStateMachine` supaya siklus escrow tetap murni; shipment berjalan
 * paralel & hanya menandai bahwa fisik kendaraan sudah sampai buyer sebelum
 * konfirmasi serah-terima escrow di-trigger.
 *
 * Alur normal:
 *   Draft → LogisticsPrep → InTransit → CustomsClearance → Delivered
 * Guard tiap transisi memverifikasi dokumen prasyarat sudah lengkap.
 *
 * Delayed adalah state paralel yang bisa masuk dari mana saja untuk menandai
 * masalah — resolusi dispute di-handle di luar state machine ini.
 */
class ShipmentStateMachine
{
    /**
     * Draft → LogisticsPrep: butuh cargo insurance + Export Declaration.
     */
    public function moveToLogisticsPrep(Shipment $shipment): void
    {
        abort_unless($shipment->status === ShipmentStatus::Draft, 409, 'Shipment tidak berada di tahap Draft.');
        abort_unless($shipment->hasCargoInsurance(), 422, 'Cargo insurance belum lengkap.');
        abort_unless(
            $this->hasDocument($shipment, ShipmentDocumentType::ExportDeclaration),
            422,
            'Export Declaration belum diunggah.'
        );

        $shipment->update(['status' => ShipmentStatus::LogisticsPrep]);
    }

    /**
     * LogisticsPrep → InTransit: butuh Bill of Lading (laut) atau Air Waybill (udara).
     * Untuk shipping_mode = land, cukup tracking_number.
     */
    public function moveToInTransit(Shipment $shipment): void
    {
        abort_unless($shipment->status === ShipmentStatus::LogisticsPrep, 409, 'Shipment belum siap dikirim.');

        $requiredDoc = match ($shipment->shipping_mode->value) {
            'sea' => ShipmentDocumentType::BillOfLading,
            'air' => ShipmentDocumentType::AirWaybill,
            'land' => null,
        };

        if ($requiredDoc !== null) {
            abort_unless(
                $this->hasDocument($shipment, $requiredDoc),
                422,
                $requiredDoc->label().' wajib diunggah sebelum shipment dinyatakan berjalan.'
            );
        }

        abort_unless(filled($shipment->tracking_number), 422, 'Nomor tracking (B/L, AWB, atau resi darat) wajib diisi.');

        $shipment->update(['status' => ShipmentStatus::InTransit]);
    }

    /**
     * InTransit → CustomsClearance: butuh Import Declaration + Customs Duty Receipt.
     * Untuk domestic (origin = destination), fase ini bisa di-skip lewat markDelivered langsung.
     */
    public function moveToCustomsClearance(Shipment $shipment): void
    {
        abort_unless($shipment->status === ShipmentStatus::InTransit, 409, 'Shipment belum dalam perjalanan.');
        abort_unless(
            $this->hasDocument($shipment, ShipmentDocumentType::ImportDeclaration),
            422,
            'Import Declaration belum diunggah.'
        );
        abort_unless(
            $this->hasDocument($shipment, ShipmentDocumentType::CustomsDutyReceipt),
            422,
            'Customs Duty Receipt (bukti bayar bea masuk) belum diunggah.'
        );

        $shipment->update(['status' => ShipmentStatus::CustomsClearance]);
    }

    /**
     * CustomsClearance (atau InTransit untuk domestic) → Delivered: butuh Proof of Delivery
     * + Letter of Acceptance dari buyer.
     */
    public function markDelivered(Shipment $shipment): void
    {
        abort_unless(
            in_array($shipment->status, [ShipmentStatus::CustomsClearance, ShipmentStatus::InTransit], true),
            409,
            'Shipment belum siap ditandai diterima.'
        );
        abort_unless(
            $this->hasDocument($shipment, ShipmentDocumentType::ProofOfDelivery),
            422,
            'Proof of Delivery dari buyer belum diunggah.'
        );
        abort_unless(
            $this->hasDocument($shipment, ShipmentDocumentType::LetterOfAcceptance),
            422,
            'Letter of Acceptance dari buyer belum diunggah.'
        );

        $shipment->update([
            'status' => ShipmentStatus::Delivered,
            'actual_arrival' => now()->toDateString(),
        ]);
    }

    public function markDelayed(Shipment $shipment): void
    {
        abort_if($shipment->status === ShipmentStatus::Delivered, 409, 'Shipment sudah diterima, tidak bisa ditandai tertunda.');
        $shipment->update(['status' => ShipmentStatus::Delayed]);
    }

    private function hasDocument(Shipment $shipment, ShipmentDocumentType $type): bool
    {
        return $shipment->documents()->where('type', $type)->exists();
    }
}
