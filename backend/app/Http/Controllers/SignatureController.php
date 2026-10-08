<?php

namespace App\Http\Controllers;

use App\Documents\DocumentGenerator;
use App\Http\Requests\SignatureRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SignatureController extends Controller
{
    /**
     * Menerima tanda tangan digital dari buyer atau seller sebagai PNG data URL
     * dari canvas signature pad frontend. Menyimpan ke disk `local`, mencatat
     * path + waktu di transaction, lalu meregenerasi SPA & Bill of Sale supaya
     * tanda tangan yang baru masuk ke PDF.
     */
    public function store(SignatureRequest $request, Transaction $transaction, DocumentGenerator $docs): TransactionResource
    {
        $this->authorize('view', $transaction);

        $user = $request->user();
        $isBuyer = $user->id === $transaction->buyer_id;
        $isSeller = $user->id === $transaction->seller_id;

        abort_unless($isBuyer || $isSeller, 403, 'Hanya buyer atau seller transaksi yang bisa menandatangani.');

        // Idempotency: kalau sudah pernah tanda tangan, tolak resign supaya bukti tidak bisa ditukar.
        if ($isBuyer && $transaction->buyer_signed_at !== null) {
            abort(409, 'Anda sudah pernah menandatangani transaksi ini.');
        }
        if ($isSeller && $transaction->seller_signed_at !== null) {
            abort(409, 'Anda sudah pernah menandatangani transaksi ini.');
        }

        $role = $isBuyer ? 'buyer' : 'seller';
        $path = 'signatures/'.$transaction->id.'/'.$role.'-'.Str::random(8).'.png';
        Storage::disk('local')->put($path, $request->binaryPng());

        $transaction->update([
            ($role.'_signature_path') => $path,
            ($role.'_signed_at') => now(),
        ]);

        // Regenerasi PDF yang memuat tanda tangan supaya bukti visual selalu up-to-date.
        $fresh = $transaction->fresh();
        $docs->generateSalesPurchaseAgreement($fresh);
        if ($fresh->escrow_status !== null && $fresh->buyer_confirmed_at && $fresh->seller_confirmed_at) {
            // Bill of Sale hanya di-regenerate kalau serah-terima sudah tercatat (BoS punya kolom
            // "Konfirmasi Pembeli/Penjual" yang harus sudah terisi).
            $docs->generateBillOfSale($fresh);
        }

        return new TransactionResource($fresh->load(['vehicle.photos', 'documents', 'statusHistories.actor']));
    }
}
