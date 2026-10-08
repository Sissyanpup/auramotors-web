<?php

namespace App\Http\Controllers;

use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerProfileDocumentController extends Controller
{
    private const ALLOWED_TYPES = [
        'ktp' => 'ktp_path',
        'npwp' => 'npwp_path',
        'company_registration' => 'company_registration_path',
        'articles_of_association' => 'articles_of_association_path',
        'ubo_declaration' => 'ubo_declaration_path',
    ];

    /**
     * Akses dokumen digate lewat signed URL (middleware `signed` di route) —
     * pola yang sama dengan `BuyerProfileDocumentController`.
     */
    public function show(Request $request, SellerProfile $sellerProfile, string $type): StreamedResponse
    {
        abort_unless(array_key_exists($type, self::ALLOWED_TYPES), 404);

        $path = $sellerProfile->{self::ALLOWED_TYPES[$type]};

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }
}
