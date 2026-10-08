<?php

namespace App\VinCheck;

use App\Enums\VinCheckStatus;
use App\Models\Vehicle;
use App\Models\VehicleVinCheck;

/**
 * Mock VIN history check — mensimulasikan integrasi Carfax/AutoCheck/database VIN
 * internasional secara offline. Response deterministik berdasarkan pola VIN
 * supaya demo/test bisa diulang. Keyword sinyal dipilih **tanpa huruf I/O/Q**
 * supaya tetap valid VIN standar internasional (yang melarang 3 huruf tsb):
 *
 *   STLN → reported_stolen (blocked)
 *   CRSH → major_accident_history (warning)
 *   RLBK → odometer_rollback_suspected (warning)
 *   SLVG → salvage_title (blocked)
 *
 * Kalau ada internet, service ini bisa diganti dengan panggilan API asli
 * mengikuti pola driver `PaymentGateway`/`DisbursementGateway` yang sudah ada.
 */
class VinCheckService
{
    public function check(Vehicle $vehicle, string $vin): VehicleVinCheck
    {
        $vin = strtoupper(trim($vin));
        [$status, $flags] = $this->evaluate($vin);

        return $vehicle->vinChecks()->create([
            'vin' => $vin,
            'status' => $status,
            'report' => [
                'flags' => $flags,
                'source' => 'mock',
                'notes' => $this->notes($status),
            ],
            'checked_at' => now(),
        ]);
    }

    /**
     * @return array{0: VinCheckStatus, 1: array<int, string>}
     */
    private function evaluate(string $vin): array
    {
        $flags = [];

        if (str_contains($vin, 'STLN')) {
            $flags[] = 'reported_stolen';
        }
        if (str_contains($vin, 'CRSH')) {
            $flags[] = 'major_accident_history';
        }
        if (str_contains($vin, 'RLBK')) {
            $flags[] = 'odometer_rollback_suspected';
        }
        if (str_contains($vin, 'SLVG')) {
            $flags[] = 'salvage_title';
        }

        if (in_array('reported_stolen', $flags, true) || in_array('salvage_title', $flags, true)) {
            return [VinCheckStatus::Blocked, $flags];
        }
        if ($flags !== []) {
            return [VinCheckStatus::Warning, $flags];
        }

        return [VinCheckStatus::Clean, []];
    }

    private function notes(VinCheckStatus $status): string
    {
        return match ($status) {
            VinCheckStatus::Clean => 'Tidak ditemukan catatan negatif dalam basis data mock.',
            VinCheckStatus::Warning => 'Ditemukan indikator yang perlu klarifikasi dari seller.',
            VinCheckStatus::Blocked => 'Kendaraan diblokir dari listing publik: indikator serius (curian/salvage).',
        };
    }
}
