<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * Helper terpusat untuk membangun signed URL dokumen. Semua download dokumen
 * privat (KYC, kendaraan, transaksi, polis asuransi) memakai signed URL supaya
 * bisa dibuka langsung di tab baru browser tanpa bergantung pada session cookie
 * Sanctum SPA yang kadang tidak ter-carry cross-port/cross-origin.
 *
 * Expiry 60 menit dipilih supaya cukup untuk admin review dokumen tanpa harus
 * refresh, tapi cukup pendek supaya URL yang bocor tidak jadi permanen.
 */
class SignedDocumentUrl
{
    private const TTL_MINUTES = 60;

    /**
     * @param  array<string, mixed>|array<int, mixed>  $params
     */
    public static function for(string $routeName, array $params): string
    {
        return URL::temporarySignedRoute($routeName, now()->addMinutes(self::TTL_MINUTES), $params);
    }
}
