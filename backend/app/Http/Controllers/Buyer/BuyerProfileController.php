<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\BuyerProfileStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\BuyerKycRequest;
use App\Http\Resources\BuyerProfileResource;
use App\Models\BuyerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BuyerProfileController extends Controller
{
    public function show(Request $request): BuyerProfileResource
    {
        $profile = $request->user()->buyerProfile;

        abort_if($profile === null, 404, 'Belum ada pengajuan KYC buyer.');

        return new BuyerProfileResource($profile);
    }

    public function store(BuyerKycRequest $request): JsonResponse
    {
        $this->authorize('create', BuyerProfile::class);

        $userId = $request->user()->id;
        $folder = 'buyer-kyc/'.$userId;

        $profile = $request->user()->buyerProfile()->create([
            'id_type' => $request->validated('id_type'),
            'id_number' => $request->validated('id_number'),
            'id_document_path' => $request->file('id_document')->store($folder, 'local'),
            'address_proof_path' => $request->file('address_proof')->store($folder, 'local'),
            'proof_of_funds_path' => $request->file('proof_of_funds')->store($folder, 'local'),
            'status' => BuyerProfileStatus::Pending,
        ]);

        return (new BuyerProfileResource($profile))->response()->setStatusCode(201);
    }

    public function update(BuyerKycRequest $request): BuyerProfileResource
    {
        $profile = $request->user()->buyerProfile;

        abort_if($profile === null, 404);

        $this->authorize('update', $profile);

        $userId = $request->user()->id;
        $folder = 'buyer-kyc/'.$userId;

        $oldPaths = [
            $profile->id_document_path,
            $profile->address_proof_path,
            $profile->proof_of_funds_path,
        ];

        $profile->update([
            'id_type' => $request->validated('id_type'),
            'id_number' => $request->validated('id_number'),
            'id_document_path' => $request->file('id_document')->store($folder, 'local'),
            'address_proof_path' => $request->file('address_proof')->store($folder, 'local'),
            'proof_of_funds_path' => $request->file('proof_of_funds')->store($folder, 'local'),
            'status' => BuyerProfileStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);

        foreach ($oldPaths as $old) {
            Storage::disk('local')->delete($old);
        }

        return new BuyerProfileResource($profile);
    }
}
