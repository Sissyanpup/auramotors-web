<?php

namespace App\Http\Controllers\Seller;

use App\Enums\SellerEntityType;
use App\Enums\SellerProfileStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerKycRequest;
use App\Http\Resources\SellerProfileResource;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SellerProfileController extends Controller
{
    public function show(Request $request): SellerProfileResource
    {
        $profile = $request->user()->sellerProfile;

        abort_if($profile === null, 404, 'Belum ada pengajuan KYC.');

        return new SellerProfileResource($profile);
    }

    public function store(SellerKycRequest $request): JsonResponse
    {
        $this->authorize('create', SellerProfile::class);

        $folder = 'kyc/'.$request->user()->id;
        $entityType = SellerEntityType::from($request->validated('entity_type'));

        $profile = $request->user()->sellerProfile()->create([
            'entity_type' => $entityType,
            'ktp_path' => $request->file('ktp')->store($folder, 'local'),
            'npwp_path' => $request->file('npwp')?->store($folder, 'local'),
            'company_registration_path' => $request->file('company_registration')?->store($folder, 'local'),
            'articles_of_association_path' => $request->file('articles_of_association')?->store($folder, 'local'),
            'ubo_declaration_path' => $request->file('ubo_declaration')?->store($folder, 'local'),
            'status' => SellerProfileStatus::Pending,
        ]);

        return (new SellerProfileResource($profile))->response()->setStatusCode(201);
    }

    public function update(SellerKycRequest $request): SellerProfileResource
    {
        $profile = $request->user()->sellerProfile;

        abort_if($profile === null, 404);

        $this->authorize('update', $profile);

        $folder = 'kyc/'.$request->user()->id;
        $entityType = SellerEntityType::from($request->validated('entity_type'));

        $oldKtp = $profile->ktp_path;
        $oldNpwp = $profile->npwp_path;
        $oldCompany = $profile->company_registration_path;
        $oldArticles = $profile->articles_of_association_path;
        $oldUbo = $profile->ubo_declaration_path;

        $profile->update([
            'entity_type' => $entityType,
            'ktp_path' => $request->file('ktp')->store($folder, 'local'),
            'npwp_path' => $request->file('npwp')?->store($folder, 'local') ?? $oldNpwp,
            'company_registration_path' => $request->file('company_registration')?->store($folder, 'local') ?? $oldCompany,
            'articles_of_association_path' => $request->file('articles_of_association')?->store($folder, 'local') ?? $oldArticles,
            'ubo_declaration_path' => $request->file('ubo_declaration')?->store($folder, 'local') ?? $oldUbo,
            'status' => SellerProfileStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);

        Storage::disk('local')->delete($oldKtp);

        return new SellerProfileResource($profile);
    }
}
