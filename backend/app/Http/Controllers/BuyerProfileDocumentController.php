<?php

namespace App\Http\Controllers;

use App\Models\BuyerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BuyerProfileDocumentController extends Controller
{
    /**
     * Akses dokumen digate lewat signed URL (middleware `signed` di route).
     * Signed URL hanya di-generate oleh `BuyerProfileResource` saat pemanggil API
     * lolos policy view — jadi hanya owner/admin yang tahu URL-nya, dan URL
     * expired dalam 60 menit sesuai `SignedDocumentUrl::TTL_MINUTES`.
     */
    public function show(Request $request, BuyerProfile $buyerProfile, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['id_document', 'address_proof', 'proof_of_funds'], true), 404);

        $path = match ($type) {
            'id_document' => $buyerProfile->id_document_path,
            'address_proof' => $buyerProfile->address_proof_path,
            'proof_of_funds' => $buyerProfile->proof_of_funds_path,
        };

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }
}
